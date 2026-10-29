<?php

namespace Modules\Class\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassSession;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $session = fn (array $attributes): ClassSession => ClassSession::withoutTenantScope()->findOrFail($attributes['session_id']);

        return [
            'session_id' => ClassSession::factory(),
            'tenant_id' => fn (array $attributes): int => $session($attributes)->tenant_id,
            'classroom_id' => fn (array $attributes): int => $session($attributes)->classroom_id,
            'user_id' => User::factory(),
            'status' => AttendanceStatus::Present,
            'recorded_at' => now(),
        ];
    }
}
