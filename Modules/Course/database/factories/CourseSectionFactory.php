<?php

namespace Modules\Course\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseSection;

/** @extends Factory<CourseSection> */
class CourseSectionFactory extends Factory
{
    protected $model = CourseSection::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'title' => fake()->sentence(3)];
    }
}
