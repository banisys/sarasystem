<?php

namespace Modules\Class\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;

/**
 * Only the row: SessionPlanner::saveSchedule() checks a schedule and
 * builds its sessions.
 *
 * @extends Factory<ClassSchedule>
 */
class ClassScheduleFactory extends Factory
{
    protected $model = ClassSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'tenant_id' => fn (array $attributes): int => Classroom::withoutTenantScope()->findOrFail($attributes['classroom_id'])->tenant_id,
            'frequency' => Frequency::Weekly,
            'interval' => 1,
            'weekdays' => [Weekday::Saturday->value],
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addMonth()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
        ];
    }
}
