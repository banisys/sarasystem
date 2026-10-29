<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Class\Database\Factories\ClassSessionFactory;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;

/**
 * One meeting of a class (ADR 0023): built from a schedule, or added by
 * hand as a make-up or extra session. Taking its attendance marks it held
 * (ADR 0024). A session changed by hand (moved,
 * cancelled, a substitute teacher, a note) is no longer rebuilt by its
 * schedule; past sessions never are.
 */
#[Fillable([
    'tenant_id', 'classroom_id', 'schedule_id', 'subject_id', 'teacher_id', 'substitute_teacher_id', 'starts_at', 'ends_at', 'location',
    'kind', 'status', 'cancel_reason', 'is_modified', 'original_starts_at', 'note',
])]
class ClassSession extends Model
{
    /** @use HasFactory<ClassSessionFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ClassSessionFactory
    {
        return ClassSessionFactory::new();
    }

    /**
     * Not cancelled.
     *
     * @param  Builder<ClassSession>  $query
     */
    public function scopeTakingPlace(Builder $query): void
    {
        $query->where('status', '!=', SessionStatus::Cancelled);
    }

    /**
     * @param  Builder<ClassSession>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>', now());
    }

    /**
     * What its schedule may still rebuild: a regular session nobody changed.
     */
    public function isUntouched(): bool
    {
        return $this->kind === SessionKind::Regular && ! $this->is_modified && $this->status === SessionStatus::Scheduled
            && $this->substitute_teacher_id === null && blank($this->note);
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->isPast();
    }

    /**
     * Who teaches it: the substitute, if any.
     */
    public function actualTeacherId(): ?int
    {
        return $this->substitute_teacher_id ?? $this->teacher_id;
    }

    public function wasMoved(): bool
    {
        return $this->original_starts_at !== null && ! $this->original_starts_at->equalTo($this->starts_at);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<ClassSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'schedule_id');
    }

    /**
     * Subjects are the primary tenant's.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_teacher_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'session_id');
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'original_starts_at' => 'datetime',
            'kind' => SessionKind::class,
            'status' => SessionStatus::class,
            'is_modified' => 'boolean',
        ];
    }
}
