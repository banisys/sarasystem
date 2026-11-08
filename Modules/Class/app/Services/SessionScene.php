<?php

namespace Modules\Class\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\ClassSession;
use Nwidart\Modules\Facades\Module;

/**
 * What the 3D view of a session shows (ADR 0049): a seat per person on the
 * session's attendance list, in the list's order, with their status; who
 * teaches it standing at the front, and the class's other staff
 * (supervisors, supporters, …) at the back, each in their class role's
 * colour and named by it. Seats aren't recorded, so a seat means nothing
 * beyond its person.
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

    /** Who teaches the session without a class role in it (a substitute from outside the class). */
    public const TEACHER_COLOR = '#6366f1';

    /**
     * Colours of the class roles that aren't attendee roles, in the order
     * the roles are defined, so a role keeps its colour in every session;
     * apart from the statuses'. The first (usually «مدرس») is the teachers'.
     */
    private const ROLE_COLORS = [self::TEACHER_COLOR, '#0ea5e9', '#ec4899', '#14b8a6', '#a855f7', '#f97316', '#84cc16'];

    public function __construct(private AttendanceRegister $register, private SessionAccess $access) {}

    /**
     * It names people and their attendance: for those who may see the class
     * or take its attendance, not everyone who sees its sessions. A cancelled
     * session has no attendance to show. The 3D views are the Campus module's
     * feature (ADR 0051).
     */
    public function canSee(User $user, ClassSession $session): bool
    {
        return (Module::find('Campus')?->isEnabled() ?? false)
            && $session->status !== SessionStatus::Cancelled
            && $session->classroom !== null
            && ($user->can('view', $session->classroom) || $this->access->isStaffOf($user, $session, 'takes_attendance'));
    }

    /**
     * @return array{
     *     virtual: bool,
     *     teachers: list<array{name: string, role: string, color: string}>,
     *     seats: list<array{name: string, status: string, label: string}>,
     *     staff: list<array{name: string, role: string, color: string}>,
     *     roles: list<array{label: string, color: string, count: int}>,
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
            ...($people = $this->people($session)),
            'seats' => $seats->all(),
            'roles' => collect([...$people['teachers'], ...$people['staff']])
                ->groupBy('role')
                ->map(fn (Collection $group, string $role): array => ['label' => $role, 'color' => $group->first()['color'], 'count' => $group->count()])
                // In the roles' order, which their colours follow.
                ->sortBy(fn (array $role): int|false => array_search($role['color'], self::ROLE_COLORS, true))
                ->values()
                ->all(),
            'statuses' => collect(self::LOOKS)
                ->filter(fn (array $look, string $status): bool => $counts->has($status))
                ->map(fn (array $look, string $status): array => [...$look, 'label' => $labels[$status], 'count' => $counts[$status]])
                ->all(),
        ];
    }

    /**
     * Who teaches the session (its teachers or their substitutes), at the
     * front, and the other members of the class on its day whose class role
     * isn't an attendee role (supervisors, supporters, …), at the back.
     * Everyone is named and coloured by their class role; someone teaching
     * without one is «مدرس». Not at the back: those replaced in this
     * session, and the teachers of the class's other sessions, who aren't
     * in this one.
     *
     * @return array{teachers: list<array{name: string, role: string, color: string}>, staff: list<array{name: string, role: string, color: string}>}
     */
    private function people(ClassSession $session): array
    {
        $day = $session->starts_at->toDateString();
        $colors = ClassRole::definedRoles()->where('is_attendee', false)->pluck('id')->values()
            ->mapWithKeys(fn (int $id, int $index): array => [$id => self::ROLE_COLORS[$index % count(self::ROLE_COLORS)]]);
        $members = ClassMember::withoutTenantScope()
            ->where('classroom_id', $session->classroom_id)
            ->whereHas('classRole', fn (Builder $role) => $role->where('is_attendee', false))
            ->where(fn (Builder $query) => $query->whereNull('joined_on')->orWhereDate('joined_on', '<=', $day))
            ->where(fn (Builder $query) => $query->whereNull('left_on')->orWhereDate('left_on', '>=', $day))
            ->with(['user', 'classRole'])
            ->get()
            // Roles in the order they were defined (ناظر before پشتیبان), then by name.
            ->sortBy([fn (ClassMember $a, ClassMember $b): int => [$a->classRole->sort, $a->class_role_id] <=> [$b->classRole->sort, $b->class_role_id], fn (ClassMember $a, ClassMember $b): int => strcmp($a->user->name, $b->user->name)])
            ->unique('user_id')
            ->keyBy('user_id');
        $person = fn (User $user): array => isset($members[$user->getKey()])
            ? ['name' => $user->name, 'role' => $members[$user->getKey()]->classRole->title, 'color' => $colors[$members[$user->getKey()]->class_role_id] ?? self::TEACHER_COLOR]
            : ['name' => $user->name, 'role' => 'مدرس', 'color' => self::TEACHER_COLOR];

        $teaching = array_map(fn (array $row): User => $row['substitute'] ?? $row['teacher'], $session->teaching());
        $inSession = collect($session->teaching())->flatMap(fn (array $row): array => array_filter([$row['teacher']->getKey(), $row['substitute']?->getKey()]));
        $otherTeachers = DB::table(ClassSession::TEACHERS_TABLE)
            ->whereIn('session_id', ClassSession::withoutTenantScope()->select('id')->where('classroom_id', $session->classroom_id)->whereKeyNot($session->getKey()))
            ->pluck('teacher_id');
        $away = $inSession->merge($otherTeachers)->map(fn (mixed $id): int => (int) $id)->unique()->all();

        return [
            'teachers' => array_map($person, $teaching),
            'staff' => $members->reject(fn (ClassMember $member): bool => in_array($member->user_id, $away, true))
                ->map(fn (ClassMember $member): array => $person($member->user))
                ->values()
                ->all(),
        ];
    }
}
