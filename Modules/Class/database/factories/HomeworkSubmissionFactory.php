<?php

namespace Modules\Class\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\HomeworkSubmission;

/**
 * @extends Factory<HomeworkSubmission>
 */
class HomeworkSubmissionFactory extends Factory
{
    protected $model = HomeworkSubmission::class;

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
            'answer' => 'پاسخ تکلیف',
            'submitted_at' => now(),
        ];
    }
}
