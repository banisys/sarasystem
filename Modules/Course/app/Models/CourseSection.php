<?php

namespace Modules\Course\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Course\Database\Factories\CourseSectionFactory;

#[Fillable(['course_id', 'title', 'position'])]
class CourseSection extends Model
{
    /** @use HasFactory<CourseSectionFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): CourseSectionFactory
    {
        return CourseSectionFactory::new();
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<CourseLesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class)->orderBy('position')->orderBy('id');
    }
}
