<?php

namespace Modules\Exam\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Exam\Enums\QuestionType;

/**
 * A served question of an attempt (a copy of the question as it was then:
 * body, options in the order shown, correct option, score) and the user's
 * answer to it. `score` is null until graded: choice answers are graded on
 * submit, as are fill-in-the-blank answers. Staff grade written answers
 * (empty ones count 0 on submit).
 *
 * @property list<array{key: string, text: string}>|null $options
 * @property list<string>|null $accepted_answers
 */
#[Fillable([
    'tenant_id', 'attempt_id', 'question_id', 'sort', 'question_type', 'question_body',
    'options', 'correct_option', 'accepted_answers', 'model_answer', 'max_score', 'selected_option', 'text_answer',
    'score', 'graded_by', 'graded_at',
])]
class AttemptAnswer extends Model
{
    use BelongsToTenant;

    protected $table = 'exam_attempt_answers';

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function isChoice(): bool
    {
        return $this->question_type === QuestionType::Choice;
    }

    public function isFillInTheBlank(): bool
    {
        return $this->question_type === QuestionType::FillInTheBlank;
    }

    public function requiresManualGrading(): bool
    {
        return $this->question_type === QuestionType::Descriptive;
    }

    public function optionText(?string $key): ?string
    {
        return $key === null ? null : (collect($this->options ?? [])->firstWhere('key', $key)['text'] ?? null);
    }

    public function correctAnswerText(): ?string
    {
        return match ($this->question_type) {
            QuestionType::Choice => $this->optionText($this->correct_option),
            QuestionType::FillInTheBlank => implode('، ', $this->accepted_answers ?? []),
            QuestionType::Descriptive => $this->model_answer,
        };
    }

    public function fillInTheBlankScore(): float
    {
        $submitted = self::normalizeAnswer($this->text_answer ?? '');

        foreach ($this->accepted_answers ?? [] as $accepted) {
            if ($submitted !== '' && $submitted === self::normalizeAnswer($accepted)) {
                return $this->max_score;
            }
        }

        return 0.0;
    }

    private static function normalizeAnswer(string $answer): string
    {
        $answer = strtr($answer, [
            'ي' => 'ی', 'ك' => 'ک',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $answer) ?? $answer));
    }

    /**
     * Score of a choice answer: full for the correct option, nothing when
     * unanswered, and with negative marking (every N wrong answers cancel
     * one right one) minus max_score / N when wrong.
     */
    public function choiceScore(?int $negativeMarking): float
    {
        return match (true) {
            $this->selected_option === null => 0.0,
            $this->selected_option === $this->correct_option => $this->max_score,
            $negativeMarking > 0 => -round($this->max_score / $negativeMarking, 2),
            default => 0.0,
        };
    }

    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'options' => 'array',
            'accepted_answers' => 'array',
            'max_score' => 'float',
            'score' => 'float',
            'graded_at' => 'datetime',
        ];
    }
}
