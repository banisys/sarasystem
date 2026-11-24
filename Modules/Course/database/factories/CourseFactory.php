<?php

namespace Modules\Course\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Models\Course;

/** @extends Factory<Course> */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['title' => fake()->sentence(3), 'description' => fake()->sentence()];
    }
}
