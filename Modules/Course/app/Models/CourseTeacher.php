<?php

namespace Modules\Course\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Course\Database\Factories\CourseTeacherFactory;
use Modules\Course\Enums\TeacherEducation;

#[Fillable(['user_id', 'education', 'field_of_study', 'institution', 'teaching_experience', 'description', 'is_active'])]
class CourseTeacher extends Model
{
    /** @use HasFactory<CourseTeacherFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): CourseTeacherFactory
    {
        return CourseTeacherFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CourseInstructor, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(CourseInstructor::class, 'user_id', 'user_id');
    }

    protected function casts(): array
    {
        return [
            'education' => TeacherEducation::class,
            'is_active' => 'boolean',
        ];
    }
}
