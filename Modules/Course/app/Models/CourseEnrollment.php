<?php

namespace Modules\Course\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Course\Database\Factories\CourseEnrollmentFactory;

#[Fillable(['course_id', 'user_id', 'starts_at', 'expires_at', 'is_revoked'])]
class CourseEnrollment extends Model
{
    /** @use HasFactory<CourseEnrollmentFactory> */
    use HasFactory;

    protected static function newFactory(): CourseEnrollmentFactory
    {
        return CourseEnrollmentFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<CourseCompletion, $this> */
    public function completions(): HasMany
    {
        return $this->hasMany(CourseCompletion::class, 'user_id', 'user_id');
    }

    public function scopeActive(Builder $query): void
    {
        $now = now();

        $query->where('is_revoked', false)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now));
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'is_revoked' => 'boolean'];
    }
}
