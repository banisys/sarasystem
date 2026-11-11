<?php

namespace Modules\Exam\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Exam\Models\QuestionTag;

/** @extends Factory<QuestionTag> */
class QuestionTagFactory extends Factory
{
    protected $model = QuestionTag::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'title' => fake()->unique()->words(2, true)];
    }
}
