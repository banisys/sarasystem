<?php

namespace Modules\Class\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;

/**
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    protected $model = ClassSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = today()->addDays(fake()->numberBetween(1, 30))->setTime(10, 0);

        return [
            'classroom_id' => Classroom::factory(),
            'tenant_id' => fn (array $attributes): int => Classroom::withoutTenantScope()->findOrFail($attributes['classroom_id'])->tenant_id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(90),
            'kind' => SessionKind::Extra,
            'status' => SessionStatus::Scheduled,
        ];
    }
}
