<?php

namespace Modules\Student\Forms;

use App\CustomFields\BuiltInField;
use App\CustomFields\CustomizableForm;
use App\CustomFields\FieldMode;
use App\CustomFields\ResolvedForm;
use App\Models\Tenant;
use Modules\Student\Models\Grade;

/**
 * The student «پیش‌ثبت‌نام» form as each organization sets it up
 * (ADR 0017, ADR 0018), for all stages and per stage. The grade, name,
 * nationality, national code, gender and mobile are always asked: the
 * one-open-preregistration rule, the SMS check and admission depend on them.
 * Defaults follow the LMS form.
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
        return 'پیش‌ثبت‌نام دانش‌آموز';
    }

    public function sections(): array
    {
        return [
            'grade' => 'مقطع و پایه',
            'personal' => 'اطلاعات دانش‌آموز',
            'contact' => 'اطلاعات تماس',
            'education' => 'سوابق تحصیلی',
        ];
    }

    public function builtInFields(): array
    {
        return [
            new BuiltInField('preferred_tenant', config('sarasystem.tenant_label').' مورد نظر', FieldMode::Optional),
            new BuiltInField('father_name', 'نام پدر'),
            new BuiltInField('birth_certificate_number', 'شماره شناسنامه'),
            new BuiltInField('birth_date', 'تاریخ تولد'),
            new BuiltInField('birth_place', 'محل تولد', FieldMode::Optional),
            new BuiltInField('phone', 'تلفن منزل'),
            new BuiltInField('email', 'ایمیل', FieldMode::Optional),
            new BuiltInField('postal_code', 'کد پستی', FieldMode::Optional),
            new BuiltInField('city', 'شهر'),
            new BuiltInField('address', 'نشانی منزل'),
            new BuiltInField('previous_school', 'نام مدرسه‌ی پیشین'),
            new BuiltInField('last_average', 'معدل ترم آخر', FieldMode::Optional),
        ];
    }

    /**
     * The form for a grade's stage of the primary tenant, or for all stages.
     */
    public static function for(Tenant $tenant, ?Grade $grade = null): ResolvedForm
    {
        return (new self)->resolve($tenant, $grade?->stage());
    }
}
