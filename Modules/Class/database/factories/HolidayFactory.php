<?php

namespace Modules\Class\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Models\Holiday;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $day = today()->addDays(fake()->numberBetween(1, 60))->toDateString();

        return [
            'tenant_id' => Tenant::factory(),
            'title' => 'تعطیلی',
            'starts_on' => $day,
            'ends_on' => $day,
        ];
    }
}
