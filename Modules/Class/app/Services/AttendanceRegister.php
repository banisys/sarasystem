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
 * Taking it marks the session held, and guardians are told about new
 * absences by SMS (ADR 0029).
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

    public function __construct(private SessionAccess $access, private AbsenceNotices $notices) {}

    /**
     * Why the user can't take the session's attendance now, or null.
     */
    public function refusal(User $user, ClassSession $session): ?string
    {
        return $this->access->refusal($user, $session, 'takes_attendance', (int) config('class.attendance.locks_after_days'), 'شما حضور و غیاب این کلاس را نمی‌گیرید.');
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

        $saved = DB::transaction(function () use ($session, $rows, $by): array {
            $saved = [];

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
                $saved[] = $record;
            }

            $session->update(['status' => SessionStatus::Held]);

            return $saved;
        });

        // Once saved: guardians hear about new absences (ADR 0029).
        $this->notices->send($session, $saved);
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
