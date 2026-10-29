<?php

namespace Modules\Student\Forms;

use App\CustomFields\BuiltInField;
use App\CustomFields\CustomizableForm;
use App\CustomFields\FieldMode;
use App\CustomFields\ResolvedForm;
use App\Models\Tenant;
use Modules\Student\Models\Program;

/**
 * The «پیش‌ثبت‌نام» form as each organization sets it up (ADR 0017,
 * ADR 0018), for all programs and per program. Built-in fields are the
 * ones every kind of learner has; what only some organizations ask (a
 * school's previous school and last average, a university's entrance
 * rank, a company's department) is added by the organization. The
 * program, name, nationality, national code, gender and mobile are always
 * asked: the one-open-preregistration rule, the SMS check and admission
 * depend on them. A school also asks the parents and whose mobile the
 * child signs in with (ADR 0028); they are not asked by default.
 */
class PreregistrationForm extends CustomizableForm
{
    public const KEY = 'student.preregistration';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'پیش‌ثبت‌نام';
    }

    public function sections(): array
    {
        return [
            'program' => 'برنامه‌ی آموزشی',
            'personal' => 'اطلاعات فردی',
            'parents' => 'والدین',
            'contact' => 'اطلاعات تماس',
            'other' => 'سایر اطلاعات',
        ];
    }

    public function builtInFields(): array
    {
        return [
            new BuiltInField('preferred_tenant', config('sarasystem.tenant_label').' مورد نظر', FieldMode::Optional),
            new BuiltInField('father_name', 'نام پدر', FieldMode::Optional),
            new BuiltInField('birth_date', 'تاریخ تولد'),
            // A school's: the parents and whose mobile the child signs in with (ADR 0028).
            new BuiltInField('father', 'مشخصات پدر (نام، کد ملی، موبایل)', FieldMode::Hidden),
            new BuiltInField('mother', 'مشخصات مادر (نام، کد ملی، موبایل)', FieldMode::Hidden),
            new BuiltInField('learner_mobile', 'موبایل خود '.config('sarasystem.learner_label'), FieldMode::Hidden),
            new BuiltInField('login_mobile', 'موبایل ورود '.config('sarasystem.learner_label').' (خودش، پدر یا مادر)', FieldMode::Hidden, [FieldMode::Required, FieldMode::Hidden]),
            new BuiltInField('phone', 'تلفن ثابت', FieldMode::Optional),
            new BuiltInField('email', 'ایمیل', FieldMode::Optional),
            new BuiltInField('postal_code', 'کد پستی', FieldMode::Optional),
            new BuiltInField('city', 'شهر', FieldMode::Optional),
            new BuiltInField('address', 'نشانی', FieldMode::Optional),
        ];
    }

    /**
     * The form for a program of the primary tenant, or for all programs.
     */
    public static function for(Tenant $tenant, ?Program $program = null): ResolvedForm
    {
        return (new self)->resolve($tenant, $program);
    }
}
