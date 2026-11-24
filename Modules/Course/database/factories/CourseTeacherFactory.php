<?php

namespace Modules\Course\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Enums\TeacherEducation;
use Modules\Course\Models\CourseTeacher;

/** @extends Factory<CourseTeacher> */
class CourseTeacherFactory extends Factory
{
    protected $model = CourseTeacher::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'education' => TeacherEducation::Bachelor,
            'field_of_study' => fake()->word(),
            'institution' => fake()->company(),
        ];
    }
}
