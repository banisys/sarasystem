<?php

namespace Modules\Student\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Student\Models\Grade;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    /**
     * A stage; use childOf() for a grade.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->unique()->word(),
            'is_active' => true,
        ];
    }

    public function childOf(Grade $stage): static
    {
        return $this->state(['tenant_id' => $stage->tenant_id, 'parent_id' => $stage->id]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
