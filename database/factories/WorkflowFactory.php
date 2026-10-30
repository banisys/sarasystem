<?php

namespace Database\Factories;

use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => 'test',
            'name' => fake()->words(2, true),
            'is_active' => true,
            'steps' => [],
        ];
    }
}
