<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Course\Database\Factories\CourseLessonFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['course_id', 'course_section_id', 'title', 'code', 'position', 'body', 'body_html', 'is_preview', 'duration_minutes', 'audio_duration_seconds'])]
class CourseLesson extends Model implements HasMedia
{
    /** @use HasFactory<CourseLessonFactory> */
    use HasFactory, InteractsWithMedia;

    public const array VIDEO_TYPES = ['video/mp4', 'video/webm'];

    public const array AUDIO_TYPES = [
        'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'application/ogg', 'audio/wav', 'audio/x-wav', 'audio/wave',
    ];

    public const array ATTACHMENT_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

    protected static function booted(): void
    {
        static::saving(function (CourseLesson $lesson): void {
            if (blank($lesson->code)) {
                $lesson->code = $lesson->getOriginal('code') ?? 'LSN-'.str()->upper(str()->random(8));
            }
        });
    }

    protected static function newFactory(): CourseLessonFactory
    {
        return CourseLessonFactory::new();
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<CourseSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    /** @return HasMany<CourseCompletion, $this> */
    public function completions(): HasMany
    {
        return $this->hasMany(CourseCompletion::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('video')->useDisk('local')->acceptsMimeTypes(self::VIDEO_TYPES)->singleFile();
        $this->addMediaCollection('audio')->useDisk('local')->acceptsMimeTypes(self::AUDIO_TYPES)->singleFile();
        $this->addMediaCollection('attachments')->useDisk('local');
    }

    protected function casts(): array
    {
        return ['is_preview' => 'boolean'];
    }
}
