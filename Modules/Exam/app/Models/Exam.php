<?php

namespace Modules\Exam\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Modules\Exam\Database\Factories\ExamFactory;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Enums\RetakePolicy;

/**
 * An online exam of one tenant (ADR 0013). Its questions are drawn from
 * question banks (sections) when an attempt starts. Only participants
 * take it: the users chosen for it, and holders of its roles in its tenant.
 */
#[Fillable([
    'tenant_id', 'title', 'description', 'is_published', 'starts_at', 'ends_at',
    'duration_minutes', 'grace_minutes', 'total_score', 'passing_score',
    'max_attempts', 'retake_policy', 'questions_per_page', 'shuffle_questions',
    'shuffle_options', 'negative_marking', 'result_visibility',
])]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ExamFactory
    {
        return ExamFactory::new();
    }

    /**
     * @return HasMany<ExamSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participantUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'exam_participant_users');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function participantRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'exam_participant_roles');
    }

    /**
     * @return HasMany<ExamAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Exams the user takes part in: chosen by name, or holding one of the
     * exam's roles in the exam's tenant.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForParticipant(Builder $query, User $user): void
    {
        $modelHasRoles = config('permission.table_names.model_has_roles');
        $userKey = config('permission.column_names.model_morph_key');

        $query->where(fn (Builder $query) => $query
            ->whereHas('participantUsers', fn (Builder $users) => $users->whereKey($user->getKey()))
            ->orWhereExists(fn (QueryBuilder $roles) => $roles
                ->selectRaw('1')
                ->from('exam_participant_roles')
                ->join($modelHasRoles, "{$modelHasRoles}.role_id", '=', 'exam_participant_roles.role_id')
                ->whereColumn('exam_participant_roles.exam_id', $query->qualifyColumn('id'))
                ->whereColumn("{$modelHasRoles}.tenant_id", $query->qualifyColumn('tenant_id'))
                ->where("{$modelHasRoles}.model_type", $user->getMorphClass())
                ->where("{$modelHasRoles}.{$userKey}", $user->getKey())));
    }

    public function hasParticipant(User $user): bool
    {
        return static::withoutTenantScope()->whereKey($this->getKey())->forParticipant($user)->exists();
    }

    public function hasStarted(?Carbon $at = null): bool
    {
        return $this->starts_at === null || $this->starts_at->lessThanOrEqualTo($at ?? now());
    }

    public function hasEnded(?Carbon $at = null): bool
    {
        return $this->ends_at !== null && $this->ends_at->lessThanOrEqualTo($at ?? now());
    }

    public function isPassing(float $score): bool
    {
        return $score >= $this->passing_score;
    }

    public function questionCount(): int
    {
        return (int) $this->sections->sum('question_count');
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'total_score' => 'float',
            'passing_score' => 'float',
            'max_attempts' => 'integer',
            'retake_policy' => RetakePolicy::class,
            'questions_per_page' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'negative_marking' => 'integer',
            'result_visibility' => ResultVisibility::class,
        ];
    }
}
