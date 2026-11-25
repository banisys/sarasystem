<?php

namespace Modules\Course\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Course\Database\Factories\CourseCompletionFactory;

#[Fillable(['course_lesson_id', 'user_id'])]
class CourseCompletion extends Model
{
    /** @use HasFactory<CourseCompletionFactory> */
    use HasFactory;

    protected static function newFactory(): CourseCompletionFactory
    {
        return CourseCompletionFactory::new();
    }

    /** @return BelongsTo<CourseLesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'course_lesson_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
