<?php

namespace Modules\Course\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Course\Database\Factories\CourseInstructorFactory;

#[Fillable(['course_id', 'user_id'])]
class CourseInstructor extends Model
{
    /** @use HasFactory<CourseInstructorFactory> */
    use HasFactory;

    protected static function newFactory(): CourseInstructorFactory
    {
        return CourseInstructorFactory::new();
    }

    /** @return Builder<User> */
    public static function eligibleUsers(): Builder
    {
        return self::withoutInactiveProfiles(User::query());
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function withoutInactiveProfiles(Builder $query): Builder
    {
        return $query->whereNotIn('users.id', CourseTeacher::query()->where('is_active', false)->select('user_id'));
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
