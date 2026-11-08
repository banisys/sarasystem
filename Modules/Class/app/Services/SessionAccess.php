<?php

namespace Modules\Class\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassSession;

/**
 * Who may work on a session, and when (ADR 0024, ADR 0025, ADR 0070):
 * the class's staff (its teachers and substitutes, and members whose class
 * role is not an attendee role: teacher, supervisor, supporter, …) at any
 * time, past, present or future; whoever may edit the class from a few
 * minutes before it starts; an attendee whose class role has the given flag
 * from then until some days after. Never on a cancelled session.
 */
class SessionAccess
{
    /**
     * Why the user can't, or null.
     *
     * @param  'takes_attendance'|'records_content'  $roleFlag
     */
    public function refusal(User $user, ClassSession $session, string $roleFlag, int $locksAfterDays, string $notAllowed): ?string
    {
        if ($session->status === SessionStatus::Cancelled) {
            return 'جلسه لغو شده است.';
        }

        if ($this->isClassStaff($user, $session)) {
            return null;
        }

        if (now()->lessThan($session->starts_at->copy()->subMinutes((int) config('class.attendance.opens_minutes_before')))) {
            return 'از کمی پیش از شروع جلسه باز می‌شود.';
        }

        if ($session->classroom !== null && $user->can('update', $session->classroom)) {
            return null;
        }

        if (! $this->isStaffOf($user, $session, $roleFlag)) {
            return $notAllowed;
        }

        if (now()->greaterThan($session->starts_at->copy()->addDays($locksAfterDays)->endOfDay())) {
            return 'مهلت ثبت برای این جلسه گذشته است؛ مدیر کلاس می‌تواند آن را تغییر دهد.';
        }

        return null;
    }

    /**
     * One of the session's teachers or substitutes, or a current member of its
     * class whose class role is not an attendee role (ADR 0070).
     */
    public function isClassStaff(User $user, ClassSession $session): bool
    {
        return $this->teachesSession($user, $session) || ClassMember::withoutTenantScope()
            ->where('classroom_id', $session->classroom_id)
            ->where('user_id', $user->getKey())
            ->current()
            ->whereHas('classRole', fn (Builder $role) => $role->where('is_attendee', false))
            ->exists();
    }

    /**
     * One of the session's teachers or substitutes, or a current member of its
     * class whose role has the flag.
     */
    public function isStaffOf(User $user, ClassSession $session, string $roleFlag): bool
    {
        if ($this->teachesSession($user, $session)) {
            return true;
        }

        return ClassMember::withoutTenantScope()
            ->where('classroom_id', $session->classroom_id)
            ->where('user_id', $user->getKey())
            ->current()
            ->whereHas('classRole', fn (Builder $role) => $role->where($roleFlag, true))
            ->exists();
    }

    private function teachesSession(User $user, ClassSession $session): bool
    {
        return $session->teachers->contains(fn (User $teacher): bool => $teacher->is($user) || $teacher->pivot->substitute_id === $user->getKey());
    }
}
