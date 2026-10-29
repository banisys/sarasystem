<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Modules\Class\Database\Factories\HomeworkSubmissionFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A learner's homework for a session, handed in from «کلاس‌های من»: an
 * answer and/or files (ADR 0030). The learner may change it until it is
 * graded; the teacher gives a score (out of the session's
 * `homework_max_score`) and feedback.
 */
#[Fillable(['tenant_id', 'classroom_id', 'session_id', 'user_id', 'answer', 'submitted_at', 'score', 'feedback', 'graded_by_id', 'graded_at'])]
class HomeworkSubmission extends Model implements HasMedia
{
    /** @use HasFactory<HomeworkSubmissionFactory> */
    use BelongsToTenant, HasFactory, InteractsWithMedia;

    protected $table = 'class_homework_submissions';

    public const FILES_COLLECTION = 'files';

    protected static function newFactory(): HomeworkSubmissionFactory
    {
        return HomeworkSubmissionFactory::new();
    }

    /**
     * Only the private disk, downloaded through signed links
     * (SessionMaterialController).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::FILES_COLLECTION)->useDisk('local');
    }

    public function isGraded(): bool
    {
        return $this->graded_at !== null;
    }

    /**
     * Handed in after the end of the due day.
     */
    public function isLate(): bool
    {
        $due = $this->session?->homework_due_on;

        return $due !== null && $this->submitted_at->greaterThan($due->copy()->endOfDay());
    }

    /**
     * Signed download links of the files, valid for a while.
     *
     * @return list<array{id: int, name: string, url: string}>
     */
    public function fileLinks(): array
    {
        return $this->getMedia(self::FILES_COLLECTION)
            ->map(fn (Media $media): array => [
                'id' => $media->getKey(),
                'name' => $media->file_name,
                'url' => URL::temporarySignedRoute('class.materials', now()->addMinutes(30), ['media' => $media->getKey()]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by_id');
    }

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }
}
