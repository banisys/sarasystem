<?php

namespace Modules\Course\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Course\Database\Factories\CourseCategoryFactory;

#[Fillable(['title'])]
class CourseCategory extends Model
{
    /** @use HasFactory<CourseCategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): CourseCategoryFactory
    {
        return CourseCategoryFactory::new();
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
