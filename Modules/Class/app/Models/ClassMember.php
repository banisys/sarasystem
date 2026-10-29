<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Class\Database\Factories\ClassMemberFactory;
use Modules\Class\Enums\AttendanceStatus;

/**
 * A person in a class with a class role, from a day to a day (ADR 0022).
 * Leaving keeps the row (with `left_on`) for the class's history.
 */
#[Fillable(['tenant_id', 'classroom_id', 'user_id', 'class_role_id', 'joined_on', 'left_on'])]
class ClassMember extends Model
{
    /** @use HasFactory<ClassMemberFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ClassMemberFactory
    {
        return ClassMemberFactory::new();
    }

    /**
     * Not left (or leaving after today).
     *
     * @param  Builder<ClassMember>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('left_on')->orWhereDate('left_on', '>=', today()));
    }

    /**
     * @param  Builder<ClassMember>  $query
     */
    public function scopeAttendees(Builder $query): void
    {
        $query->whereHas('classRole', fn (Builder $role) => $role->where('is_attendee', true));
    }

    public function hasLeft(): bool
    {
        return $this->left_on !== null && $this->left_on->isBefore(today());
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Roles are the primary tenant's.
     *
     * @return BelongsTo<ClassRole, $this>
     */
    public function classRole(): BelongsTo
    {
        return $this->belongsTo(ClassRole::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * The member's attendance in this class; the caller adds the class
     * (`classroom_id`), e.g. ClassMember::withAttendanceCounts().
     *
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'user_id', 'user_id');
    }

    /**
     * Adds `absences_count`, `excused_count` and `lates_count` in the member's class.
     *
     * @param  Builder<ClassMember>  $query
     */
    public function scopeWithAttendanceCounts(Builder $query): void
    {
        $inClass = fn (AttendanceStatus $status): \Closure => fn (Builder $attendances) => $attendances
            ->whereColumn('class_attendances.classroom_id', 'class_members.classroom_id')
            ->where('status', $status);

        $query->withCount([
            'attendances as absences_count' => $inClass(AttendanceStatus::Absent),
            'attendances as excused_count' => $inClass(AttendanceStatus::Excused),
            'attendances as lates_count' => $inClass(AttendanceStatus::Late),
        ]);
    }

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }
}
