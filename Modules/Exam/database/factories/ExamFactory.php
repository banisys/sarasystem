<?php

namespace Modules\Exam\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Enums\RetakePolicy;
use Modules\Exam\Models\Exam;

/**
 * A published exam, open now, with no questions or participants yet.
 *
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => 'آزمون '.fake()->word(),
            'is_published' => true,
            'duration_minutes' => 30,
            'grace_minutes' => 3,
            'total_score' => 20,
            'passing_score' => 12,
            'max_attempts' => 1,
            'retake_policy' => RetakePolicy::Always,
            'questions_per_page' => 5,
            'result_visibility' => ResultVisibility::ScoreWithAnswers,
        ];
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }
}
