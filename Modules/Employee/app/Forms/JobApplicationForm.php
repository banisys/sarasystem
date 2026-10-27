<?php

namespace Modules\Employee\Forms;

use App\CustomFields\BuiltInField;
use App\CustomFields\CustomizableForm;
use App\CustomFields\FieldMode;
use App\CustomFields\ResolvedForm;
use App\Models\Tenant;
use Modules\Employee\Models\JobPosition;

/**
 * The «همکاری با ما» form as each organization sets it up (ADR 0017), for
 * all positions and per job position. The job, name, nationality, national
 * code, gender and mobile are always asked: the one-open-application rule,
 * the SMS check, military status and hiring depend on them.
 */
class JobApplicationForm extends CustomizableForm
{
    public const KEY = 'employee.job_application';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'همکاری با ما';
    }

    public function sections(): array
    {
        return [
            'personal' => 'اطلاعات فردی',
            'contact' => 'اطلاعات تماس',
            'education' => 'سوابق تحصیلی',
            'work' => 'سوابق کاری',
            'resume' => 'رزومه',
        ];
    }

    public function builtInFields(): array
    {
        return [
            new BuiltInField('father_name', 'نام پدر'),
            new BuiltInField('birth_date', 'تاریخ تولد'),
            new BuiltInField('military_status', 'وضعیت نظام وظیفه (آقایان ایرانی)'),
            new BuiltInField('phone', 'تلفن ثابت', FieldMode::Optional),
            new BuiltInField('email', 'ایمیل'),
            new BuiltInField('postal_code', 'کد پستی'),
            new BuiltInField('address', 'نشانی'),
            new BuiltInField('education', 'سوابق تحصیلی'),
            new BuiltInField('work_history', 'سوابق کاری', FieldMode::Optional),
            new BuiltInField('resume', 'فایل رزومه'),
        ];
    }

    /**
     * The form for a position of the primary tenant, or for all positions.
     */
    public static function for(Tenant $tenant, ?JobPosition $position = null): ResolvedForm
    {
        return (new self)->resolve($tenant, $position);
    }
}
