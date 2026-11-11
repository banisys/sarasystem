<?php

namespace Modules\Exam\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Exam\Models\QuestionCategory;

/** @extends Factory<QuestionCategory> */
class QuestionCategoryFactory extends Factory
{
    protected $model = QuestionCategory::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'title' => fake()->words(2, true)];
    }
}
