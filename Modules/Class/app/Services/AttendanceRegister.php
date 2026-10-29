<?php

namespace Modules\Class\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassSession;

/**
 * Taking a session's attendance (ADR 0024). It is taken for the members
 * whose class role is an attendee role and who were in the class on the
 * session's day. It opens a little before the session starts. Its teacher
 * (or substitute) and the class's members whose role takes attendance may
 * take it until a few days after; whoever may edit the class, at any time.
 * Taking it marks the session held.
 */
class AttendanceRegister
{
    /**
     * Who is on the session's list, by name, with the users who already
     * have a record there (e.g. left the class since).
     *
     * @return Collection<int, User>
     */
    public function attendees(ClassSession $session): Collection
    {
        $day = $session->starts_at->toDateString();

        $memberIds = ClassMember::withoutTenantScope()
            ->where('classroom_id', $session->classroom_id)
            ->whereHas('classRole', fn (Builder $role) => $role->where('is_attendee', true))
            ->where(fn (Builder $query) => $query->whereNull('joined_on')->orWhereDate('joined_on', '<=', $day))
            ->where(fn (Builder $query) => $query->whereNull('left_on')->orWhereDate('left_on', '>=', $day))
            ->pluck('user_id');
        $recordedIds = Attendance::withoutTenantScope()->where('session_id', $session->getKey())->pluck('user_id');

        return User::query()->whereKey($memberIds->merge($recordedIds)->unique())->orderBy('name')->get();
    }

    /**
     * Why the user can't take the session's attendance now, or null.
     */
    public function refusal(User $user, ClassSession $session): ?string
    {
        if ($session->status === SessionStatus::Cancelled) {
            return 'جلسه لغو شده است.';
        }

        if (now()->lessThan($session->starts_at->copy()->subMinutes((int) config('class.attendance.opens_minutes_before')))) {
            return 'حضور و غیاب از کمی پیش از شروع جلسه باز می‌شود.';
        }

        if ($session->classroom !== null && $user->can('update', $session->classroom)) {
            return null;
        }

        if (! $this->takesAttendance($user, $session)) {
            return 'شما حضور و غیاب این کلاس را نمی‌گیرید.';
        }

        if (now()->greaterThan($session->starts_at->copy()->addDays((int) config('class.attendance.locks_after_days'))->endOfDay())) {
            return 'مهلت ثبت حضور و غیاب این جلسه گذشته است؛ مدیر کلاس می‌تواند آن را تغییر دهد.';
        }

        return null;
    }

    public function canTake(User $user, ClassSession $session): bool
    {
        return $this->refusal($user, $session) === null;
    }

    /**
     * @param  array<int, array{status: AttendanceStatus|string, late_minutes?: int|string|null, note?: string|null}>  $rows  user id => row
     *
     * @throws ClassException
     */
    public function record(ClassSession $session, array $rows, User $by): void
    {
        if (($refusal = $this->refusal($by, $session)) !== null) {
            throw new ClassException($refusal);
        }

        $allowed = $this->attendees($session)->modelKeys();
        if (array_diff(array_keys($rows), $allowed) !== []) {
            throw new ClassException('فقط حضور و غیاب شرکت‌کنندگان این جلسه ثبت می‌شود.');
        }

        DB::transaction(function () use ($session, $rows, $by): void {
            foreach ($rows as $userId => $row) {
                $status = $row['status'] instanceof AttendanceStatus ? $row['status'] : AttendanceStatus::from($row['status']);
                $record = Attendance::withoutTenantScope()->firstOrNew(['session_id' => $session->getKey(), 'user_id' => $userId]);
                $changed = ! $record->exists || $record->status !== $status
                    || $record->late_minutes !== self::lateMinutes($status, $row) || $record->note !== self::note($row);

                if (! $changed) {
                    continue;
                }

                $record->fill([
                    'tenant_id' => $session->tenant_id,
                    'classroom_id' => $session->classroom_id,
                    'status' => $status,
                    'late_minutes' => self::lateMinutes($status, $row),
                    'note' => self::note($row),
                    'recorded_by_id' => $by->getKey(),
                    'recorded_at' => now(),
                ])->save();
            }

            $session->update(['status' => SessionStatus::Held]);
        });
    }

    /**
     * The session's teacher or substitute, or a current member of its
     * class whose role takes attendance.
     */
    private function takesAttendance(User $user, ClassSession $session): bool
    {
        if (in_array($user->getKey(), [$session->teacher_id, $session->substitute_teacher_id], true)) {
            return true;
        }

        return ClassMember::withoutTenantScope()
            ->where('classroom_id', $session->classroom_id)
            ->where('user_id', $user->getKey())
            ->current()
            ->whereHas('classRole', fn (Builder $role) => $role->where('takes_attendance', true))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function lateMinutes(AttendanceStatus $status, array $row): ?int
    {
        return $status === AttendanceStatus::Late && filled($row['late_minutes'] ?? null) ? (int) $row['late_minutes'] : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function note(array $row): ?string
    {
        return filled($row['note'] ?? null) ? trim($row['note']) : null;
    }
}
