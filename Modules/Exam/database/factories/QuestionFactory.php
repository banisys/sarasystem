<?php

namespace Modules\Exam\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Models\Question;

/**
 * A choice question with options a–d, "a" being correct.
 *
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => QuestionType::Choice,
            'body' => fake()->sentence().'؟',
            'options' => collect(['a', 'b', 'c', 'd'])->map(fn (string $key): array => [
                'key' => $key,
                'text' => "گزینه {$key}",
                'is_correct' => $key === 'a',
            ])->all(),
            'score' => 1,
            'is_active' => true,
        ];
    }

    public function descriptive(): static
    {
        return $this->state(['type' => QuestionType::Descriptive, 'options' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
