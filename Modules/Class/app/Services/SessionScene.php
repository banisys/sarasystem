<?php

namespace Modules\Class\Services;

use App\Models\User;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassSession;

/**
 * What the 3D view of a session shows (ADR 0049): a seat per person on the
 * session's attendance list, in the list's order, with their status. Seats
 * aren't recorded, so a seat means nothing beyond its person.
 */
class SessionScene
{
    private const UNRECORDED = 'unrecorded';

    /**
     * How each status looks. `seated`: a figure sits there; otherwise the
     * chair is empty, with a `marker` on the desk or in the status's colour.
     *
     * @var array<string, array{color: string, seated: bool, marker: bool}>
     */
    private const LOOKS = [
        'present' => ['color' => '#22c55e', 'seated' => true, 'marker' => false],
        'late' => ['color' => '#f59e0b', 'seated' => true, 'marker' => false],
        'absent' => ['color' => '#ef4444', 'seated' => false, 'marker' => true],
        'excused' => ['color' => '#94a3b8', 'seated' => false, 'marker' => false],
        self::UNRECORDED => ['color' => '#cbd5e1', 'seated' => true, 'marker' => false],
    ];

    public function __construct(private AttendanceRegister $register, private SessionAccess $access) {}

    /**
     * It names people and their attendance: for those who may see the class
     * or take its attendance, not everyone who sees its sessions. A cancelled
     * session has no attendance to show.
     */
    public function canSee(User $user, ClassSession $session): bool
    {
        return $session->status !== SessionStatus::Cancelled
            && $session->classroom !== null
            && ($user->can('view', $session->classroom) || $this->access->isStaffOf($user, $session, 'takes_attendance'));
    }

    /**
     * @return array{
     *     virtual: bool,
     *     teacher: string|null,
     *     seats: list<array{name: string, status: string, label: string}>,
     *     statuses: array<string, array{label: string, color: string, seated: bool, marker: bool, count: int}>,
     * }
     */
    public function for(ClassSession $session): array
    {
        $recorded = $session->attendances()->get()->keyBy('user_id');

        $seats = $this->register->attendees($session)->map(function (User $user) use ($recorded): array {
            $attendance = $recorded[$user->getKey()] ?? null;
            $status = $attendance?->status;

            return [
                'name' => $user->name,
                'status' => $status->value ?? self::UNRECORDED,
                'label' => match (true) {
                    $status === null => 'ثبت‌نشده',
                    $status === AttendanceStatus::Late && $attendance->late_minutes !== null => $status->getLabel().' '.$attendance->late_minutes.' دقیقه',
                    default => $status->getLabel(),
                },
            ];
        })->values();

        $counts = $seats->countBy('status');
        $labels = [
            ...collect(AttendanceStatus::cases())->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => $status->getLabel()])->all(),
            self::UNRECORDED => 'ثبت‌نشده',
        ];

        return [
            'virtual' => $session->classroom?->mode === ClassMode::Online,
            'teacher' => ($session->substituteTeacher ?? $session->teacher)?->name,
            'seats' => $seats->all(),
            'statuses' => collect(self::LOOKS)
                ->filter(fn (array $look, string $status): bool => $counts->has($status))
                ->map(fn (array $look, string $status): array => [...$look, 'label' => $labels[$status], 'count' => $counts[$status]])
                ->all(),
        ];
    }
}
