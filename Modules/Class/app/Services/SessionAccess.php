<?php

namespace Modules\Class\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassSession;

/**
 * Who may work on a session, and when (ADR 0024, ADR 0025): from a few
 * minutes before it starts, its teacher or substitute and the class's
 * members whose class role has the given flag, until some days after;
 * whoever may edit the class, at any time. Never on a cancelled session.
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
     * The session's teacher or substitute, or a current member of its
     * class whose role has the flag.
     */
    public function isStaffOf(User $user, ClassSession $session, string $roleFlag): bool
    {
        if (in_array($user->getKey(), [$session->teacher_id, $session->substitute_teacher_id], true)) {
            return true;
        }

        return ClassMember::withoutTenantScope()
            ->where('classroom_id', $session->classroom_id)
            ->where('user_id', $user->getKey())
            ->current()
            ->whereHas('classRole', fn (Builder $role) => $role->where($roleFlag, true))
            ->exists();
    }
}
