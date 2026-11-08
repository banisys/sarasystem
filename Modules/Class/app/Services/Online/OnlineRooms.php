<?php

namespace Modules\Class\Services\Online;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Class\Enums\OnlinePlatform;
use Modules\Class\Models\ClassOnlineRoom;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\OnlineServer;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\SessionCalendar;

/**
 * Where a class meets online, and changing it (ADR 0073). A class has
 * rooms, each from a day on; a session meets in the room in effect on its
 * day. Moving to another platform in the middle of a term adds a room from
 * that day, so the sessions before it keep theirs (and their recordings).
 */
class OnlineRooms
{
    /**
     * The choices of the class form: «لینک» and each active server
     * («server:3»), plus the server the class uses now even if inactive.
     *
     * @return array<string, string>
     */
    public static function choices(?string $current = null): array
    {
        $servers = OnlineServer::query()->active()->orderBy('title')->get();

        if ($current !== null && str_starts_with($current, 'server:') && ! $servers->contains('id', (int) substr($current, 7))) {
            $servers->push(...OnlineServer::query()->whereKey((int) substr($current, 7))->get());
        }

        return [OnlinePlatform::Link->value => 'لینک (Google Meet، Skyroom یا هر نشانی دیگر)']
            + $servers->mapWithKeys(fn (OnlineServer $server): array => ['server:'.$server->getKey() => $server->displayName().($server->is_active ? '' : ' (غیرفعال)')])->all();
    }

    /**
     * The room in effect on the day: the last one that started by then.
     */
    public function on(Classroom $classroom, CarbonInterface $day): ?ClassOnlineRoom
    {
        return self::pick($classroom->onlineRooms, $day);
    }

    public function forSession(ClassSession $session): ?ClassOnlineRoom
    {
        if ($session->classroom === null || ! $session->classroom->mode->meetsOnline()) {
            return null;
        }

        $room = $this->on($session->classroom, $session->starts_at);

        return $room?->isUsable() ? $room : null;
    }

    /**
     * Meet at the choice, «link» with its address or «server:{id}», from
     * the day on. Rooms planned after that day are dropped. If the room in
     * effect that day has the same choice, only its link changes. Otherwise,
     * with no session before the day the class simply has the new room from
     * the beginning; with some, it gets a room from that day.
     *
     * Without a day (the class form when the platform stays): the room in
     * effect today keeps its place and only its link changes; a different
     * choice starts today.
     *
     * A new room on a platform that offers it may be made per session
     * (ADR 0074); a room kept as it is keeps its way.
     *
     * @throws ClassException
     */
    public function change(Classroom $classroom, string $choice, ?string $meetingUrl, ?CarbonInterface $from = null, ?User $actor = null, bool $perSession = false): void
    {
        [$platform, $server] = $this->parse($choice);
        $keepsPlan = $from === null;
        $from = CarbonImmutable::parse($from ?? today())->startOfDay();
        $meetingUrl = $platform === OnlinePlatform::Link ? (filled($meetingUrl) ? trim($meetingUrl) : null) : null;
        $perSession = $perSession && $platform->offersRoomPerSession();

        DB::transaction(function () use ($classroom, $choice, $platform, $server, $from, $keepsPlan, $meetingUrl, $actor, $perSession): void {
            if (! $keepsPlan) {
                $classroom->onlineRooms()->whereDate('starts_on', '>', $from)->delete();
            }

            $rooms = $classroom->onlineRooms()->lockForUpdate()->get();
            $effective = self::pick($rooms, $from) ?? $rooms->sortBy(fn (ClassOnlineRoom $room) => $room->starts_on?->timestamp ?? 0)->last();
            $attributes = ['platform' => $platform, 'server_id' => $server?->getKey(), 'per_session' => $perSession, 'meeting_url' => $meetingUrl, 'created_by_id' => $actor?->getKey()];

            if ($effective !== null && $effective->choice() === $choice) {
                if ($platform === OnlinePlatform::Link && $effective->meeting_url !== $meetingUrl) {
                    $effective->update(['meeting_url' => $meetingUrl]);
                }

                return;
            }

            if ($rooms->isEmpty() || ! $classroom->sessions()->where('starts_at', '<', $from)->exists()) {
                $classroom->onlineRooms()->delete();
                $classroom->onlineRooms()->create([...$attributes, 'starts_on' => null]);
            } else {
                $classroom->onlineRooms()->whereDate('starts_on', '>=', $from)->delete();
                $classroom->onlineRooms()->create([...$attributes, 'starts_on' => $from]);
            }

            $classroom->unsetRelation('onlineRooms');
        });
    }

    /**
     * «از ابتدا: Adobe Connect — سرور اول», «از ۱۴۰۵/۰۸/۱۷: لینک https://…».
     *
     * @return list<string>
     */
    public function history(Classroom $classroom): array
    {
        return $classroom->onlineRooms()->with('server')->get()
            ->sortBy(fn (ClassOnlineRoom $room) => $room->starts_on?->timestamp ?? 0)
            ->map(fn (ClassOnlineRoom $room): string => ($room->starts_on ? 'از '.SessionCalendar::jalali($room->starts_on->toImmutable(), 'Y/m/d') : 'از ابتدا')
                .': '.$room->description()
                .($room->platform === OnlinePlatform::Link ? ' '.($room->meeting_url ?? '(بدون نشانی)') : ''))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ClassOnlineRoom>  $rooms
     */
    private static function pick(Collection $rooms, CarbonInterface $day): ?ClassOnlineRoom
    {
        return $rooms
            ->filter(fn (ClassOnlineRoom $room): bool => $room->starts_on === null || $room->starts_on->lessThanOrEqualTo($day))
            ->sortBy(fn (ClassOnlineRoom $room) => $room->starts_on?->timestamp ?? 0)
            ->last();
    }

    /**
     * @return array{0: OnlinePlatform, 1: ?OnlineServer}
     *
     * @throws ClassException
     */
    private function parse(string $choice): array
    {
        if ($choice === OnlinePlatform::Link->value) {
            return [OnlinePlatform::Link, null];
        }

        $server = str_starts_with($choice, 'server:') ? OnlineServer::query()->find((int) substr($choice, 7)) : null;

        if ($server === null) {
            throw new ClassException('سرور کلاس آنلاین پیدا نشد.');
        }

        return [$server->platform, $server];
    }
}
