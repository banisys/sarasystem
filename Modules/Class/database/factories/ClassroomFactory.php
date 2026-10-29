<?php

namespace Modules\Class\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Models\Classroom;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => 'کلاس '.fake()->unique()->numberBetween(1, 9999),
            'mode' => ClassMode::InPerson,
            'is_active' => true,
        ];
    }
}
