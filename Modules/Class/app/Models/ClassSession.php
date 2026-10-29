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
 * (ADR 0024, ADR 0025). A session changed by hand (moved,
 * cancelled, a substitute teacher, a note) is no longer rebuilt by its
 * schedule; past sessions never are.
 */
#[Fillable([
    'tenant_id', 'classroom_id', 'schedule_id', 'subject_id', 'teacher_id', 'substitute_teacher_id', 'starts_at', 'ends_at', 'location',
    'kind', 'status', 'cancel_reason', 'is_modified', 'original_starts_at', 'note',
    'covered_topic_ids', 'summary', 'homework', 'homework_due_on', 'homework_max_score', 'content_recorded_by_id', 'content_recorded_at',
])]
class ClassSession extends Model implements HasMedia
{
    /** @use HasFactory<ClassSessionFactory> */
    use BelongsToTenant, HasFactory, InteractsWithMedia;

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
            && $this->substitute_teacher_id === null && blank($this->note) && $this->content_recorded_at === null;
    }

    public function hasContent(): bool
    {
        return $this->content_recorded_at !== null;
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
