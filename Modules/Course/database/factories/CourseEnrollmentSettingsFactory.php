<?php

namespace Modules\Course\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Models\CourseEnrollmentSettings;

/** @extends Factory<CourseEnrollmentSettings> */
class CourseEnrollmentSettingsFactory extends Factory
{
    protected $model = CourseEnrollmentSettings::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [];
    }
}
