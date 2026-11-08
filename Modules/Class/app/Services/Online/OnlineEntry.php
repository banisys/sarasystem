<?php

namespace Modules\Class\Services\Online;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionAccess;

/**
 * Who enters a session's online room, as what, and when (ADR 0073). The
 * class's staff (ADR 0070) and whoever may edit the class enter as hosts at
 * any time; its attendees as participants, from a few minutes before the
 * session until it ends. Nobody enters a cancelled session.
 */
class OnlineEntry
{
    public function __construct(private readonly OnlineRooms $rooms, private readonly SessionAccess $access) {}

    /**
     * True as host, false as participant, null not at all.
     */
    public function asHost(User $user, ClassSession $session): ?bool
    {
        if ($this->access->isClassStaff($user, $session) || ($session->classroom !== null && $user->can('update', $session->classroom))) {
            return true;
        }

        $isAttendee = ClassMember::withoutTenantScope()
            ->where('classroom_id', $session->classroom_id)
            ->where('user_id', $user->getKey())
            ->where(fn (Builder $query) => $query->whereNull('joined_on')->orWhereDate('joined_on', '<=', $session->starts_at))
            ->where(fn (Builder $query) => $query->whereNull('left_on')->orWhereDate('left_on', '>=', $session->starts_at))
            ->attendees()
            ->exists();

        return $isAttendee ? false : null;
    }

    /**
     * Whether to offer the person a way in (not whether it is open yet).
     */
    public function offers(User $user, ClassSession $session): bool
    {
        return $session->status !== SessionStatus::Cancelled
            && $this->rooms->forSession($session) !== null
            && $this->asHost($user, $session) !== null;
    }

    /**
     * The address that takes the person into the session's room.
     *
     * @throws OnlineClassException
     */
    public function enter(User $user, ClassSession $session): string
    {
        $room = $this->rooms->forSession($session);

        if ($room === null) {
            throw new OnlineClassException('این جلسه کلاس آنلاین ندارد.');
        }

        if ($session->status === SessionStatus::Cancelled) {
            throw new OnlineClassException('جلسه لغو شده است.');
        }

        $asHost = $this->asHost($user, $session);

        if ($asHost === null) {
            throw new OnlineClassException('شما در این جلسه نیستید.');
        }

        if (! $asHost) {
            if (now()->lessThan($session->starts_at->copy()->subMinutes((int) config('class.online.opens_minutes_before')))) {
                throw new OnlineClassException('کلاس آنلاین از '.config('class.online.opens_minutes_before').' دقیقه پیش از شروع جلسه ('.$session->starts_at->format('H:i').') باز می‌شود.');
            }

            if (now()->greaterThan($session->ends_at)) {
                throw new OnlineClassException('این جلسه تمام شده است.');
            }
        }

        return $room->platform->driver()->enter($room, $user, $asHost);
    }
}
