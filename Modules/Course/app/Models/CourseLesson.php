<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Course\Database\Factories\CourseLessonFactory;
use Modules\Course\Enums\CourseLessonType;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['course_id', 'course_section_id', 'title', 'type', 'position', 'body', 'is_preview', 'duration_minutes'])]
class CourseLesson extends Model implements HasMedia
{
    /** @use HasFactory<CourseLessonFactory> */
    use HasFactory, InteractsWithMedia;

    public const array VIDEO_TYPES = ['video/mp4', 'video/webm'];

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
        $this->addMediaCollection('attachments')->useDisk('local');
    }

    protected function casts(): array
    {
        return ['is_preview' => 'boolean', 'type' => CourseLessonType::class];
    }
}
