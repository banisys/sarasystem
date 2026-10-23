<?php

namespace Modules\Exam\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Exam\Database\Factories\ExamAttemptFactory;
use Modules\Exam\Enums\AttemptStatus;

/**
 * One sitting of an exam by a user. Its answers hold a copy of the served
 * questions, so later edits to questions don't change it. Answers are
 * accepted until deadline_at plus the exam's grace minutes.
 *
 * `score` is on the exam's scale (total_score) and set once every answer
 * is graded; `raw_score` / `max_raw_score` are the sums of question scores.
 */
#[Fillable([
    'tenant_id', 'exam_id', 'user_id', 'status', 'started_at', 'deadline_at',
    'submitted_at', 'timed_out', 'raw_score', 'max_raw_score', 'score',
])]
class ExamAttempt extends Model
{
    /** @use HasFactory<ExamAttemptFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ExamAttemptFactory
    {
        return ExamAttemptFactory::new();
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id')->orderBy('sort');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', AttemptStatus::InProgress);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeFinished(Builder $query): void
    {
        $query->where('status', '!=', AttemptStatus::InProgress);
    }

    public function isInProgress(): bool
    {
        return $this->status === AttemptStatus::InProgress;
    }

    /**
     * Past the deadline and its grace time: no more answers are taken.
     */
    public function isOverdue(): bool
    {
        return now()->greaterThan($this->deadline_at->copy()->addMinutes($this->exam->grace_minutes));
    }

    /**
     * Whether the attempt passed; null while it is not fully graded.
     */
    public function passed(): ?bool
    {
        return $this->score === null ? null : $this->exam->isPassing($this->score);
    }

    /**
     * Sum the answers' scores. With any answer still ungraded the attempt
     * waits for review; otherwise it gets its final score on the exam's
     * scale (never below zero, even with negative marking).
     */
    public function recalculate(): void
    {
        $answers = $this->answers()->get(['score', 'max_score']);
        $maxRawScore = (float) $answers->sum('max_score');
        $rawScore = (float) $answers->sum('score');
        $isGraded = $answers->every(fn (AttemptAnswer $answer): bool => $answer->score !== null);

        $this->forceFill([
            'raw_score' => $rawScore,
            'max_raw_score' => $maxRawScore,
            'status' => $isGraded ? AttemptStatus::Completed : AttemptStatus::PendingReview,
            'score' => match (true) {
                ! $isGraded => null,
                $maxRawScore <= 0 => 0.0,
                default => round($this->exam->total_score * max(0.0, $rawScore) / $maxRawScore, 2),
            },
        ])->save();
    }

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'timed_out' => 'boolean',
            'raw_score' => 'float',
            'max_raw_score' => 'float',
            'score' => 'float',
        ];
    }
}
