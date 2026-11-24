<?php

namespace Modules\Course\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Course\Models\CourseCategory;

/** @extends Factory<CourseCategory> */
class CourseCategoryFactory extends Factory
{
    protected $model = CourseCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['title' => fake()->unique()->word()];
    }
}
