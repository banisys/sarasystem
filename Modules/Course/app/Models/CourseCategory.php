<?php

namespace Modules\Course\Models;

use App\Models\Concerns\HasCategoryParent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Course\Database\Factories\CourseCategoryFactory;

#[Fillable(['title', 'parent_id', 'alias', 'short_description', 'position', 'is_active', 'locale', 'translation_key', 'metadata'])]
class CourseCategory extends Model
{
    /** @use HasFactory<CourseCategoryFactory> */
    use HasCategoryParent, HasFactory;

    protected static function newFactory(): CourseCategoryFactory
    {
        return CourseCategoryFactory::new();
    }

    /** @return BelongsTo<CourseCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class);
    }

    /** @return HasMany<CourseCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected function categoryTreeIsTenantScoped(): bool
    {
        return false;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'metadata' => 'object'];
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** @return BelongsToMany<Course, $this> */
    public function additionalCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_category_course');
    }
}
