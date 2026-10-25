<?php

namespace Modules\Employee\Http\Requests;

use App\Models\Tenant;
use App\Rules\IranianMobile;
use App\Rules\JalaliDate;
use App\Rules\NationalCode;
use App\Support\Identifiers;
use Ariaieboy\Jalali\Jalali;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Employee\Enums\EducationLevel;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Enums\MilitaryStatus;
use Modules\Employee\Enums\Nationality;
use Modules\Employee\Enums\UniversityType;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Support\ApplicantMobile;

/**
 * The public «همکاری با ما» form (ADR 0014). Rules follow the LMS form, with
 * the work history, home phone and konkur rank optional, military status
 * asked of Iranian men only and the university asked for university degrees only.
 * The mobile is confirmed by SMS beforehand (CareersController::sendCode/verifyCode).
 */
class StoreJobApplicationRequest extends FormRequest
{
    public const MAX_EDUCATION_ROWS = 10;

    public const MAX_WORK_ROWS = 15;

    public const RESUME_MAX_KILOBYTES = 10240;

    /**
     * Allowed Jalali birth years: 16 to 69 years old, as in LMS.
     *
     * @return array{int, int}
     */
    public static function birthYearRange(): array
    {
        $year = Jalali::now()->getYear();

        return [$year - 69, $year - 16];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Persian digits are accepted everywhere a number is typed.
     */
    protected function prepareForValidation(): void
    {
        $latin = fn (mixed $value): mixed => is_string($value) ? Identifiers::toLatinDigits(trim($value)) : $value;

        $this->merge([
            'national_code' => $latin($this->input('national_code')),
            'mobile' => Identifiers::normalizeMobile($latin($this->input('mobile'))) ?? $this->input('mobile'),
            'phone' => $latin($this->input('phone')),
            'postal_code' => $latin($this->input('postal_code')),
            'birth_date' => $latin($this->input('birth_date')),
            'education' => array_map(fn ($row): mixed => is_array($row) ? array_map($latin, $row) : $row, (array) $this->input('education', [])),
            'work_history' => array_map(fn ($row): mixed => is_array($row) ? array_map($latin, $row) : $row, (array) $this->input('work_history', [])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        [$minBirthYear, $maxBirthYear] = self::birthYearRange();
        $universityLevels = collect(EducationLevel::cases())->filter->isUniversity()->map->value->all();

        return [
            'job_position_id' => ['required', Rule::exists('employee_job_positions', 'id')
                ->where('tenant_id', Tenant::primary()?->getKey())
                ->where('is_active', true)],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'father_name' => ['required', 'string', 'max:50'],
            'nationality' => ['required', Rule::enum(Nationality::class)],
            'national_code' => $this->isIranian()
                ? ['required', 'string', new NationalCode]
                : ['required', 'digits:12'],
            'birth_date' => ['required', 'string', new JalaliDate($minBirthYear, $maxBirthYear)],
            'gender' => ['required', Rule::enum(Gender::class)],
            'military_status' => [Rule::requiredIf($this->asksMilitaryStatus()), 'nullable', Rule::enum(MilitaryStatus::class)],
            'mobile' => ['required', 'string', new IranianMobile],
            'phone' => ['nullable', 'digits:11'],
            'email' => ['required', 'email', 'max:100'],
            'postal_code' => ['required', 'digits:10'],
            'address' => ['required', 'string', 'min:10', 'max:300'],

            'education' => ['required', 'array', 'min:1', 'max:'.self::MAX_EDUCATION_ROWS],
            'education.*.level' => ['required', Rule::enum(EducationLevel::class)],
            'education.*.major' => ['required', 'string', 'max:100'],
            'education.*.orientation' => ['nullable', 'string', 'max:100'],
            'education.*.average' => ['required', 'numeric', 'min:0', 'max:20'],
            'education.*.rank' => ['nullable', 'integer', 'min:1'],
            'education.*.graduation_date' => ['required', 'string', new JalaliDate],
            'education.*.university_type' => ['nullable', 'required_if:education.*.level,'.implode(',', $universityLevels), Rule::enum(UniversityType::class)],
            'education.*.university' => ['nullable', 'required_if:education.*.level,'.implode(',', $universityLevels), 'string', 'max:150'],

            'work_history' => ['nullable', 'array', 'max:'.self::MAX_WORK_ROWS],
            'work_history.*.company_name' => ['required', 'string', 'max:150'],
            'work_history.*.job_position' => ['required', 'string', 'max:150'],
            'work_history.*.start_date' => ['required', 'string', new JalaliDate],
            'work_history.*.end_date' => ['nullable', 'string', new JalaliDate],
            'work_history.*.termination_reason' => ['nullable', 'string', 'max:300'],
            'work_history.*.company_phone' => ['nullable', 'string', 'max:20'],

            'resume' => ['required', 'file', 'mimes:pdf', 'max:'.self::RESUME_MAX_KILOBYTES],
        ];
    }

    /**
     * A job may not end before it starts. The mobile must have been
     * confirmed by SMS in this session, and an open application may only be
     * updated from the mobile it was sent with (ADR 0014).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('work_history', []) as $index => $row) {
                $start = JalaliDate::toDate(Arr::get($row, 'start_date'));
                $end = JalaliDate::toDate(Arr::get($row, 'end_date'));

                if ($start && $end && $end->lessThan($start)) {
                    $validator->errors()->add("work_history.{$index}.end_date", 'تاریخ پایان همکاری نباید پیش از تاریخ شروع باشد.');
                }
            }
        }, function (Validator $validator): void {
            if ($validator->errors()->hasAny(['mobile', 'national_code'])) {
                return;
            }

            $mobile = $this->input('mobile');

            if ($mobile !== app(ApplicantMobile::class)->verified()) {
                $validator->errors()->add('mobile', 'شماره موبایل را با کد پیامکی تأیید کنید.');

                return;
            }

            $open = JobApplication::openFor($this->input('national_code'));

            if ($open !== null && $open->mobile !== $mobile) {
                $validator->errors()->add('national_code', 'برای این کد ملی درخواستی با شماره‌ی '.ApplicantMobile::mask($open->mobile).' ثبت شده است. برای ویرایش آن با همان شماره اقدام کنید.');
            }
        }];
    }

    /**
     * The application's attributes: Jalali dates as Gregorian, fields that
     * don't apply emptied.
     *
     * @return array<string, mixed>
     */
    public function applicationData(): array
    {
        $data = $this->safe()->except(['resume', 'job_position_id']);
        $universityFields = fn (array $row): bool => EducationLevel::from($row['level'])->isUniversity();

        return [
            ...$data,
            'birth_date' => JalaliDate::toDate($data['birth_date']),
            'military_status' => $this->asksMilitaryStatus() ? $data['military_status'] : null,
            'education' => array_map(fn (array $row): array => [
                'level' => $row['level'],
                'major' => $row['major'],
                'orientation' => $row['orientation'] ?? null,
                'average' => (float) $row['average'],
                'rank' => filled($row['rank'] ?? null) ? (int) $row['rank'] : null,
                'graduation_date' => JalaliDate::toDate($row['graduation_date'])->toDateString(),
                'university_type' => $universityFields($row) ? $row['university_type'] : null,
                'university' => $universityFields($row) ? $row['university'] : null,
            ], $data['education']),
            'work_history' => array_map(fn (array $row): array => [
                'company_name' => $row['company_name'],
                'job_position' => $row['job_position'],
                'start_date' => JalaliDate::toDate($row['start_date'])->toDateString(),
                'end_date' => JalaliDate::toDate($row['end_date'] ?? null)?->toDateString(),
                'termination_reason' => $row['termination_reason'] ?? null,
                'company_phone' => $row['company_phone'] ?? null,
            ], $data['work_history'] ?? []),
        ];
    }

    public function isIranian(): bool
    {
        return $this->input('nationality') !== Nationality::Foreign->value;
    }

    public function asksMilitaryStatus(): bool
    {
        return $this->isIranian() && $this->input('gender') === Gender::Male->value;
    }

    /**
     * The app has no Persian validation translations yet.
     *
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute را وارد کنید.',
            'required_if' => ':attribute را وارد کنید.',
            'string' => ':attribute معتبر نیست.',
            'max' => [
                'string' => ':attribute نباید بیشتر از :max حرف باشد.',
                'numeric' => ':attribute نباید بیشتر از :max باشد.',
                'array' => 'بیشتر از :max مورد در «:attribute» نمی‌شود.',
                'file' => 'حجم :attribute نباید بیشتر از ۱۰ مگابایت باشد.',
            ],
            'min' => [
                'string' => ':attribute باید دست‌کم :min حرف باشد.',
                'numeric' => ':attribute نباید کمتر از :min باشد.',
                'array' => 'دست‌کم یک مورد در «:attribute» وارد کنید.',
            ],
            'digits' => ':attribute باید :digits رقم باشد.',
            'numeric' => ':attribute باید عدد باشد.',
            'integer' => ':attribute باید عدد صحیح باشد.',
            'email' => ':attribute معتبر نیست.',
            'exists' => ':attribute انتخاب‌شده معتبر نیست.',
            'enum' => ':attribute انتخاب‌شده معتبر نیست.',
            'file' => ':attribute باید فایل باشد.',
            'mimes' => ':attribute باید فایل PDF باشد.',
            'array' => ':attribute معتبر نیست.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'job_position_id' => 'شغل درخواستی',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'father_name' => 'نام پدر',
            'nationality' => 'ملیت',
            'national_code' => $this->isIranian() ? 'کد ملی' : 'کد اتباع',
            'birth_date' => 'تاریخ تولد',
            'gender' => 'جنسیت',
            'military_status' => 'وضعیت نظام وظیفه',
            'mobile' => 'تلفن همراه',
            'phone' => 'تلفن ثابت',
            'email' => 'ایمیل',
            'postal_code' => 'کد پستی',
            'address' => 'نشانی',
            'education' => 'سوابق تحصیلی',
            'education.*.level' => 'مقطع',
            'education.*.major' => 'رشته',
            'education.*.orientation' => 'گرایش',
            'education.*.average' => 'معدل',
            'education.*.rank' => 'رتبه کنکور',
            'education.*.graduation_date' => 'تاریخ اخذ مدرک',
            'education.*.university_type' => 'نوع دانشگاه',
            'education.*.university' => 'نام دانشگاه',
            'work_history' => 'سوابق کاری',
            'work_history.*.company_name' => 'محل کار',
            'work_history.*.job_position' => 'سمت',
            'work_history.*.start_date' => 'تاریخ شروع همکاری',
            'work_history.*.end_date' => 'تاریخ پایان همکاری',
            'work_history.*.termination_reason' => 'علت پایان همکاری',
            'work_history.*.company_phone' => 'تلفن محل کار',
            'resume' => 'فایل رزومه',
        ];
    }
}
