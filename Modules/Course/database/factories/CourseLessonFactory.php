<?php

namespace Modules\Course\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;

/** @extends Factory<CourseLesson> */
class CourseLessonFactory extends Factory
{
    protected $model = CourseLesson::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'course_section_id' => CourseSection::factory(),
            'course_id' => fn (array $attributes): int => CourseSection::query()->findOrFail($attributes['course_section_id'])->course_id,
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
        ];
    }
}
