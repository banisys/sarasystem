<?php

namespace Modules\Course\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseInstructor;

/** @extends Factory<CourseInstructor> */
class CourseInstructorFactory extends Factory
{
    protected $model = CourseInstructor::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'user_id' => User::factory()];
    }
}
