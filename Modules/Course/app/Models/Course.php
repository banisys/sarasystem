<?php

namespace Modules\Course\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Course\Database\Factories\CourseFactory;

#[Fillable([
    'course_category_id', 'title', 'description', 'is_published', 'allows_self_enrollment',
    'code', 'type', 'capacity', 'description_html', 'cover_image', 'video_cover', 'intro_video',
    'gallery_images', 'starts_on', 'ends_on', 'prepayment_amount',
])]
class Course extends Model
{
    public const array IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    protected static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }

    /** @return BelongsTo<CourseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    /** @return BelongsToMany<CourseCategory, $this> */
    public function additionalCategories(): BelongsToMany
    {
        return $this->belongsToMany(CourseCategory::class, 'course_category_course');
    }

    /** @return HasMany<CourseSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<CourseLesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class);
    }

    /** @return HasMany<CourseEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /** @return HasMany<CourseInstructor, $this> */
    public function instructors(): HasMany
    {
        return $this->hasMany(CourseInstructor::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'allows_self_enrollment' => 'boolean',
            'gallery_images' => 'array',
        ];
    }
}
