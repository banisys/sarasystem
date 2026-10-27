<?php

namespace Modules\Student\Http\Requests;

use App\CustomFields\ResolvedForm;
use App\Models\Tenant;
use App\Rules\IranianMobile;
use App\Rules\JalaliDate;
use App\Rules\NationalCode;
use App\Support\Identifiers;
use Ariaieboy\Jalali\Jalali;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\Nationality;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Models\Grade;
use Modules\Student\Models\Preregistration;
use Modules\Student\Support\GuardianMobile;

/**
 * The public student «پیش‌ثبت‌نام» form (ADR 0018). Rules follow the LMS
 * form; the Tehran district and Kanoon fields are dropped and the captcha
 * is replaced by an SMS check of the mobile (PreregistrationController::sendCode/verifyCode).
 * Which of the other fields are asked, and the added fields, follow the
 * organization's setup of the form for the chosen stage (ADR 0017).
 */
class StorePreregistrationRequest extends FormRequest
{
    public const UNDER_REVIEW_MESSAGE = 'پیش‌ثبت‌نام شما در حال بررسی است و دیگر نمی‌توان آن را تغییر داد.';

    private ?ResolvedForm $preregistrationForm = null;

    private Grade|false|null $grade = false;

    /**
     * Allowed Jalali birth years: 4 to 19 years old, as in LMS.
     *
     * @return array{int, int}
     */
    public static function birthYearRange(): array
    {
        $year = Jalali::now()->getYear();

        return [$year - 19, $year - 4];
    }

    /**
     * The tenants a family may name as the school they would like: the
     * active ones besides the primary tenant (usually a head office), or
     * the primary tenant alone in a single-school installation.
     *
     * @return Collection<int, Tenant>
     */
    public static function preferredTenants(): Collection
    {
        $tenants = Tenant::query()->active()->orderBy('name')->get(['id', 'name', 'is_primary']);
        $others = $tenants->reject(fn (Tenant $tenant): bool => (bool) $tenant->is_primary)->values();

        return $others->isNotEmpty() ? $others : $tenants;
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
            'birth_certificate_number' => $latin($this->input('birth_certificate_number')),
            'last_average' => $latin($this->input('last_average')),
            ResolvedForm::INPUT => $this->preregistrationForm()->prepareCustomInput((array) $this->input(ResolvedForm::INPUT, [])),
        ]);
    }

    /**
     * The chosen grade, or the chosen stage when it has no grades; null
     * while nothing valid is chosen.
     */
    public function grade(): ?Grade
    {
        if ($this->grade === false) {
            $tenant = Tenant::primary() ?? abort(404);
            $grades = Grade::withoutTenantScope()->where('tenant_id', $tenant->getKey())->active();
            $stage = is_numeric($this->input('stage_id')) ? (clone $grades)->stages()->find($this->input('stage_id')) : null;

            $this->grade = match (true) {
                $stage === null => null,
                is_numeric($this->input('grade_id')) => (clone $grades)->where('parent_id', $stage->getKey())->find($this->input('grade_id')),
                default => (clone $grades)->where('parent_id', $stage->getKey())->exists() ? null : $stage,
            };
        }

        return $this->grade;
    }

    /**
     * The form as set up for the chosen stage, or for all stages while
     * none (or an unknown one) is chosen.
     */
    public function preregistrationForm(): ResolvedForm
    {
        if ($this->preregistrationForm === null) {
            $tenant = Tenant::primary() ?? abort(404);
            $stage = is_numeric($this->input('stage_id'))
                ? Grade::withoutTenantScope()->where('tenant_id', $tenant->getKey())->active()->stages()->find($this->input('stage_id'))
                : null;

            $this->preregistrationForm = PreregistrationForm::for($tenant, $stage);
        }

        return $this->preregistrationForm;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        [$minBirthYear, $maxBirthYear] = self::birthYearRange();
        $form = $this->preregistrationForm();
        $primaryId = Tenant::primary()?->getKey();

        return [
            'stage_id' => ['required', Rule::exists('student_grades', 'id')
                ->where('tenant_id', $primaryId)
                ->whereNull('parent_id')
                ->where('is_active', true)],
            'grade_id' => ['nullable', 'integer'],
            'preferred_tenant_id' => $form->rulesFor('preferred_tenant', [Rule::in(self::preferredTenants()->modelKeys())]),
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'father_name' => $form->rulesFor('father_name', ['string', 'max:50']),
            'nationality' => ['required', Rule::enum(Nationality::class)],
            'national_code' => $this->isIranian()
                ? ['required', 'string', new NationalCode]
                : ['required', 'digits:12'],
            'birth_certificate_number' => $form->rulesFor('birth_certificate_number', ['string', 'max:50']),
            'birth_date' => $form->rulesFor('birth_date', ['string', new JalaliDate($minBirthYear, $maxBirthYear)]),
            'birth_place' => $form->rulesFor('birth_place', ['string', 'max:100']),
            'gender' => ['required', Rule::enum(Gender::class)],
            'mobile' => ['required', 'string', new IranianMobile],
            'phone' => $form->rulesFor('phone', ['digits:11']),
            'email' => $form->rulesFor('email', ['email', 'max:100']),
            'postal_code' => $form->rulesFor('postal_code', ['digits:10']),
            'city' => $form->rulesFor('city', ['string', 'max:100']),
            'address' => $form->rulesFor('address', ['string', 'min:10', 'max:300']),
            'previous_school' => $form->rulesFor('previous_school', ['string', 'max:150']),
            'last_average' => $form->rulesFor('last_average', ['numeric', 'min:0', 'max:20']),
            ...$form->customRules(),
        ];
    }

    /**
     * A stage with grades needs one of its grades. The mobile must have
     * been confirmed by SMS in this session, and an open preregistration
     * may only be updated from the mobile it was sent with, and only until
     * a reviewer has it (ADR 0016).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('stage_id') && $this->grade() === null) {
                $validator->errors()->add('grade_id', 'پایه‌ی تحصیلی را انتخاب کنید.');
            }
        }, function (Validator $validator): void {
            if ($validator->errors()->hasAny(['mobile', 'national_code'])) {
                return;
            }

            $mobile = $this->input('mobile');

            if ($mobile !== app(GuardianMobile::class)->verified()) {
                $validator->errors()->add('mobile', 'شماره موبایل را با کد پیامکی تأیید کنید.');

                return;
            }

            $open = Preregistration::openFor($this->input('national_code'));

            if ($open !== null && $open->mobile !== $mobile) {
                $validator->errors()->add('national_code', 'برای این کد ملی پیش‌ثبت‌نامی با شماره‌ی '.GuardianMobile::mask($open->mobile).' ثبت شده است. برای ویرایش آن با همان شماره اقدام کنید.');
            } elseif ($open !== null && ! $open->canBeUpdatedByApplicant()) {
                $validator->errors()->add('national_code', self::UNDER_REVIEW_MESSAGE);
            }
        }];
    }

    /**
     * The preregistration's attributes: the Jalali date as Gregorian, fields
     * that are not asked emptied, the added fields' answers with a copy of
     * each field.
     *
     * @return array<string, mixed>
     */
    public function preregistrationData(): array
    {
        $data = $this->safe()->except(['stage_id', ResolvedForm::INPUT]);
        $grade = $this->grade();

        return [
            ...array_fill_keys(['preferred_tenant_id', 'father_name', 'birth_certificate_number', 'birth_place', 'phone', 'email', 'postal_code', 'city', 'address', 'previous_school', 'last_average'], null),
            ...$data,
            'grade_id' => $grade->getKey(),
            'grade_title' => $grade->fullTitle(),
            'birth_date' => JalaliDate::toDate($data['birth_date'] ?? null),
            'custom_fields' => $this->preregistrationForm()->answers((array) $this->validated(ResolvedForm::INPUT, [])),
        ];
    }

    public function isIranian(): bool
    {
        return $this->input('nationality') !== Nationality::Foreign->value;
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
            'string' => ':attribute معتبر نیست.',
            'max' => [
                'string' => ':attribute نباید بیشتر از :max حرف باشد.',
                'numeric' => ':attribute نباید بیشتر از :max باشد.',
                'array' => 'بیشتر از :max مورد در «:attribute» نمی‌شود.',
            ],
            'min' => [
                'string' => ':attribute باید دست‌کم :min حرف باشد.',
                'numeric' => ':attribute نباید کمتر از :min باشد.',
                'array' => 'دست‌کم یک مورد در «:attribute» وارد کنید.',
            ],
            'digits' => ':attribute باید :digits رقم باشد.',
            'numeric' => ':attribute باید عدد باشد.',
            'integer' => ':attribute معتبر نیست.',
            'email' => ':attribute معتبر نیست.',
            'exists' => ':attribute انتخاب‌شده معتبر نیست.',
            'enum' => ':attribute انتخاب‌شده معتبر نیست.',
            'array' => ':attribute معتبر نیست.',
            'in' => ':attribute انتخاب‌شده معتبر نیست.',
            'boolean' => ':attribute معتبر نیست.',
            'accepted' => '«:attribute» را تیک بزنید.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'stage_id' => 'مقطع تحصیلی',
            'grade_id' => 'پایه‌ی تحصیلی',
            'preferred_tenant_id' => config('sarasystem.tenant_label').' مورد نظر',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'father_name' => 'نام پدر',
            'nationality' => 'ملیت',
            'national_code' => $this->isIranian() ? 'کد ملی' : 'کد اتباع',
            'birth_certificate_number' => 'شماره شناسنامه',
            'birth_date' => 'تاریخ تولد',
            'birth_place' => 'محل تولد',
            'gender' => 'جنسیت',
            'mobile' => 'تلفن همراه',
            'phone' => 'تلفن منزل',
            'email' => 'ایمیل',
            'postal_code' => 'کد پستی',
            'city' => 'شهر',
            'address' => 'نشانی منزل',
            'previous_school' => 'نام مدرسه‌ی پیشین',
            'last_average' => 'معدل ترم آخر',
            ...$this->preregistrationForm()->customAttributes(),
        ];
    }
}
