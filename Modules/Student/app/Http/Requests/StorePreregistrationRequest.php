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
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Support\PreregistrationMobile;

/**
 * The public «پیش‌ثبت‌نام» form (ADR 0018). The built-in fields are the
 * ones every kind of learner has, with the LMS student form's rules; the
 * captcha is replaced by an SMS check of the mobile
 * (PreregistrationController::sendCode/verifyCode). Which of them are
 * asked, and the added fields, follow the organization's setup of the
 * form for the chosen program (ADR 0017).
 */
class StorePreregistrationRequest extends FormRequest
{
    public const UNDER_REVIEW_MESSAGE = 'پیش‌ثبت‌نام شما در حال بررسی است و دیگر نمی‌توان آن را تغییر داد.';

    private ?ResolvedForm $preregistrationForm = null;

    /**
     * Allowed Jalali birth years: 3 to 90 years old, for any kind of learner.
     *
     * @return array{int, int}
     */
    public static function birthYearRange(): array
    {
        $year = Jalali::now()->getYear();

        return [$year - 90, $year - 3];
    }

    /**
     * The tenants an applicant may name as the one they would like: the
     * active ones besides the primary tenant (usually a head office), or
     * the primary tenant alone in a single-tenant installation.
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
            ResolvedForm::INPUT => $this->preregistrationForm()->prepareCustomInput((array) $this->input(ResolvedForm::INPUT, [])),
        ]);
    }

    /**
     * The form as set up for the chosen program, or for all programs while
     * none (or an unknown one) is chosen.
     */
    public function preregistrationForm(): ResolvedForm
    {
        if ($this->preregistrationForm === null) {
            $tenant = Tenant::primary() ?? abort(404);
            $program = is_numeric($this->input('program_id'))
                ? Program::withoutTenantScope()->where('tenant_id', $tenant->getKey())->active()->find($this->input('program_id'))
                : null;

            $this->preregistrationForm = PreregistrationForm::for($tenant, $program);
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

        return [
            'program_id' => ['required', Rule::exists('student_programs', 'id')
                ->where('tenant_id', Tenant::primary()?->getKey())
                ->where('is_active', true)],
            'preferred_tenant_id' => $form->rulesFor('preferred_tenant', [Rule::in(self::preferredTenants()->modelKeys())]),
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'father_name' => $form->rulesFor('father_name', ['string', 'max:50']),
            'nationality' => ['required', Rule::enum(Nationality::class)],
            'national_code' => $this->isIranian()
                ? ['required', 'string', new NationalCode]
                : ['required', 'digits:12'],
            'birth_date' => $form->rulesFor('birth_date', ['string', new JalaliDate($minBirthYear, $maxBirthYear)]),
            'gender' => ['required', Rule::enum(Gender::class)],
            'mobile' => ['required', 'string', new IranianMobile],
            'phone' => $form->rulesFor('phone', ['digits:11']),
            'email' => $form->rulesFor('email', ['email', 'max:100']),
            'postal_code' => $form->rulesFor('postal_code', ['digits:10']),
            'city' => $form->rulesFor('city', ['string', 'max:100']),
            'address' => $form->rulesFor('address', ['string', 'min:10', 'max:300']),
            ...$form->customRules(),
        ];
    }

    /**
     * The mobile must have been confirmed by SMS in this session, and an
     * open preregistration may only be updated from the mobile it was sent
     * with, and only until a reviewer has it (ADR 0016).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['mobile', 'national_code'])) {
                return;
            }

            $mobile = $this->input('mobile');

            if ($mobile !== app(PreregistrationMobile::class)->verified()) {
                $validator->errors()->add('mobile', 'شماره موبایل را با کد پیامکی تأیید کنید.');

                return;
            }

            $open = Preregistration::openFor($this->input('national_code'));

            if ($open !== null && $open->mobile !== $mobile) {
                $validator->errors()->add('national_code', 'برای این کد ملی پیش‌ثبت‌نامی با شماره‌ی '.PreregistrationMobile::mask($open->mobile).' ثبت شده است. برای ویرایش آن با همان شماره اقدام کنید.');
            } elseif ($open !== null && ! $open->canBeUpdatedByApplicant()) {
                $validator->errors()->add('national_code', self::UNDER_REVIEW_MESSAGE);
            }
        }];
    }

    /**
     * The chosen program; only after validation.
     */
    public function program(): Program
    {
        return Program::withoutTenantScope()->findOrFail($this->validated('program_id'));
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
        $data = $this->safe()->except([ResolvedForm::INPUT]);

        return [
            ...array_fill_keys(['preferred_tenant_id', 'father_name', 'phone', 'email', 'postal_code', 'city', 'address'], null),
            ...$data,
            'program_title' => $this->program()->title,
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
            'program_id' => 'برنامه‌ی آموزشی',
            'preferred_tenant_id' => config('sarasystem.tenant_label').' مورد نظر',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'father_name' => 'نام پدر',
            'nationality' => 'ملیت',
            'national_code' => $this->isIranian() ? 'کد ملی' : 'کد اتباع',
            'birth_date' => 'تاریخ تولد',
            'gender' => 'جنسیت',
            'mobile' => 'تلفن همراه',
            'phone' => 'تلفن ثابت',
            'email' => 'ایمیل',
            'postal_code' => 'کد پستی',
            'city' => 'شهر',
            'address' => 'نشانی',
            ...$this->preregistrationForm()->customAttributes(),
        ];
    }
}
