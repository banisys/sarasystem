<?php

namespace Modules\Course\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Models\CourseCompletion;
use Modules\Course\Models\CourseLesson;

/** @extends Factory<CourseCompletion> */
class CourseCompletionFactory extends Factory
{
    protected $model = CourseCompletion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['course_lesson_id' => CourseLesson::factory(), 'user_id' => User::factory()];
    }
}
