<?php

namespace Modules\Class\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Models\TeachingPlan;

/**
 * @extends Factory<TeachingPlan>
 */
class TeachingPlanFactory extends Factory
{
    protected $model = TeachingPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => 'طرح درس '.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }

    /**
     * Topics «سرفصل ۱» … taking the given number of sessions each.
     *
     * @param  list<int>  $sessions
     */
    public function withTopics(array $sessions): static
    {
        return $this->afterCreating(function (TeachingPlan $plan) use ($sessions): void {
            foreach ($sessions as $index => $count) {
                $plan->topics()->create(['sort' => $index + 1, 'title' => 'سرفصل '.($index + 1), 'sessions' => $count]);
            }
        });
    }
}
