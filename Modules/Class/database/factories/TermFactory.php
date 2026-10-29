<?php

namespace Modules\Class\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Models\Term;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => 'دوره‌ی '.fake()->unique()->numberBetween(1, 9999),
            'starts_on' => today()->subMonth()->toDateString(),
            'ends_on' => today()->addMonths(8)->toDateString(),
            'is_active' => true,
        ];
    }
}
