<?php

namespace Modules\Course\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Course\Database\Factories\CourseEnrollmentSettingsFactory;

#[Fillable(['manual_name', 'manual_enabled', 'free_name', 'free_enabled'])]
class CourseEnrollmentSettings extends Model
{
    /** @use HasFactory<CourseEnrollmentSettingsFactory> */
    use BelongsToTenant, HasFactory;

    protected $attributes = [
        'manual_name' => 'ثبت‌نام دستی',
        'manual_enabled' => true,
        'free_name' => 'ثبت‌نام رایگان',
        'free_enabled' => true,
    ];

    public static function current(): self
    {
        return self::query()->firstOrNew();
    }

    protected static function newFactory(): CourseEnrollmentSettingsFactory
    {
        return CourseEnrollmentSettingsFactory::new();
    }

    protected function casts(): array
    {
        return ['manual_enabled' => 'boolean', 'free_enabled' => 'boolean'];
    }
}
