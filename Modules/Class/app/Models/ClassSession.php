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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\URL;
use Modules\Class\Database\Factories\ClassSessionFactory;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One meeting of a class (ADR 0023): built from a schedule, or added by
 * hand as a make-up or extra session. Taking its attendance or recording
 * what it covered (topics, summary, homework, materials) marks it held
 * (ADR 0024, ADR 0025). It has any number of teachers, each of whom
 * may be replaced by a substitute (ADR 0068). A session changed by hand
 * (moved, cancelled, its own teachers, a substitute, a note) is no longer
 * rebuilt by its schedule; past sessions never are.
 */
#[Fillable([
    'tenant_id', 'classroom_id', 'schedule_id', 'subject_id', 'starts_at', 'ends_at', 'location',
    'kind', 'status', 'cancel_reason', 'is_modified', 'original_starts_at', 'note',
    'covered_topic_ids', 'summary', 'homework', 'homework_due_on', 'homework_max_score', 'content_recorded_by_id', 'content_recorded_at',
])]
class ClassSession extends Model implements HasMedia
{
    /** @use HasFactory<ClassSessionFactory> */
    use BelongsToTenant, HasFactory, InteractsWithMedia;

    /** Its teachers, each with an optional substitute (ADR 0068). */
    public const TEACHERS_TABLE = 'class_session_teachers';

    /** Files handed out in the session or with its homework (ADR 0025). */
    public const MATERIALS_COLLECTION = 'materials';

    /**
     * Only the private disk, downloaded through signed links
     * (SessionMaterialController).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MATERIALS_COLLECTION)->useDisk('local');
    }

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
     * Taught by the user: as a substitute, or as a teacher nobody replaces
     * (actualTeacherIds()).
     *
     * @param  Builder<ClassSession>  $query
     */
    public function scopeTaughtBy(Builder $query, User $user): void
    {
        $query->whereExists(fn (QueryBuilder $row) => $row->from(self::TEACHERS_TABLE)
            ->whereColumn(self::TEACHERS_TABLE.'.session_id', 'class_sessions.id')
            ->whereRaw('coalesce(substitute_id, teacher_id) = ?', [$user->getKey()]));
    }

    /**
     * One of its teachers or substitutes, replaced or not.
     *
     * @param  Builder<ClassSession>  $query
     */
    public function scopeWithTeacher(Builder $query, User $user): void
    {
        $query->whereExists(fn (QueryBuilder $row) => $row->from(self::TEACHERS_TABLE)
            ->whereColumn(self::TEACHERS_TABLE.'.session_id', 'class_sessions.id')
            ->where(fn (QueryBuilder $row) => $row->where('teacher_id', $user->getKey())->orWhere('substitute_id', $user->getKey())));
    }

    /**
     * On the user's list as a learner: their class role there is an attendee
     * role and they were in the class on the day, or their attendance was
     * taken (as AttendanceRegister::attendees()).
     *
     * @param  Builder<ClassSession>  $query
     */
    public function scopeAttendedBy(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereHas('classroom.members', fn (Builder $member) => $member
                ->where('user_id', $user->getKey())
                ->attendees()
                ->whereRaw('(class_members.joined_on is null or class_members.joined_on <= cast(class_sessions.starts_at as date))')
                ->whereRaw('(class_members.left_on is null or class_members.left_on >= cast(class_sessions.starts_at as date))'))
            ->orWhereHas('attendances', fn (Builder $attendance) => $attendance->where('user_id', $user->getKey())));
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
            && ! $this->hasSubstitute() && blank($this->note) && $this->content_recorded_at === null;
    }

    public function hasContent(): bool
    {
        return $this->content_recorded_at !== null;
    }

    /**
     * Not running now and nothing recorded, past ones too (ADR 0066).
     * Attendance marks a session held, and homework comes with content, so
     * this needs no query.
     */
    public function canBeDeleted(): bool
    {
        $isRunning = $this->status === SessionStatus::Scheduled && $this->hasStarted() && $this->ends_at->isFuture();

        return ! $isRunning && $this->status !== SessionStatus::Held && ! $this->hasContent();
    }

    /**
     * Signed download links of the materials, valid for a while.
     *
     * @return list<array{id: int, name: string, url: string}>
     */
    public function materialLinks(): array
    {
        return $this->getMedia(self::MATERIALS_COLLECTION)
            ->map(fn (Media $media): array => [
                'id' => $media->getKey(),
                'name' => $media->file_name,
                'url' => URL::temporarySignedRoute('class.materials', now()->addMinutes(30), ['media' => $media->getKey()]),
            ])
            ->values()
            ->all();
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->isPast();
    }

    /**
     * Who teaches it: each teacher, or their substitute.
     *
     * @return list<int>
     */
    public function actualTeacherIds(): array
    {
        return $this->teachers
            ->map(fn (User $teacher): int => $teacher->pivot->substitute_id ?? $teacher->getKey())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Its teachers and the substitutes standing in for them, in order.
     *
     * @return list<array{teacher: User, substitute: User|null}>
     */
    public function teaching(): array
    {
        return $this->teachers
            ->map(fn (User $teacher): array => [
                'teacher' => $teacher,
                'substitute' => $teacher->pivot->substitute_id === null
                    ? null
                    : $this->substitutes->first(fn (User $substitute): bool => $substitute->pivot->teacher_id === $teacher->getKey()),
            ])
            ->values()
            ->all();
    }

    /**
     * E.g. «مریم احمدی، سارا رضایی (جایگزین علی کریمی)»; null without teachers.
     */
    public function teacherNames(): ?string
    {
        $names = array_map(
            fn (array $row): string => $row['substitute'] ? $row['substitute']->name.' (جایگزین '.$row['teacher']->name.')' : $row['teacher']->name,
            $this->teaching(),
        );

        return $names === [] ? null : implode('، ', $names);
    }

    public function hasSubstitute(): bool
    {
        return $this->teachers->contains(fn (User $teacher): bool => $teacher->pivot->substitute_id !== null);
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
     * Pivot: `substitute_id`, who teaches instead of them (ADR 0068).
     *
     * @return BelongsToMany<User, $this>
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, self::TEACHERS_TABLE, 'session_id', 'teacher_id')
            ->withPivot(['id', 'substitute_id'])
            ->orderBy(self::TEACHERS_TABLE.'.id');
    }

    /**
     * Pivot: `teacher_id`, whom they stand in for.
     *
     * @return BelongsToMany<User, $this>
     */
    public function substitutes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, self::TEACHERS_TABLE, 'session_id', 'substitute_id')
            ->withPivot('teacher_id');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'session_id');
    }

    /**
     * Learners' homework for this session (ADR 0030).
     *
     * @return HasMany<HomeworkSubmission, $this>
     */
    public function homeworkSubmissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class, 'session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function contentRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'content_recorded_by_id');
    }

    protected function casts(): array
    {
        return [
            'covered_topic_ids' => 'array',
            'homework_due_on' => 'date',
            'homework_max_score' => 'integer',
            'content_recorded_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'original_starts_at' => 'datetime',
            'kind' => SessionKind::class,
            'status' => SessionStatus::class,
            'is_modified' => 'boolean',
        ];
    }
}
