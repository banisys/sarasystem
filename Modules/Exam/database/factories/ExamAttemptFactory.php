<?php

namespace Modules\Exam\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Exam\Enums\AttemptStatus;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;

/**
 * A running attempt with no answers; tests usually start attempts through
 * ExamTaking instead.
 *
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'tenant_id' => fn (array $attributes): int => Exam::withoutTenantScope()->findOrFail($attributes['exam_id'])->tenant_id,
            'user_id' => User::factory(),
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'deadline_at' => now()->addMinutes(30),
        ];
    }
}
