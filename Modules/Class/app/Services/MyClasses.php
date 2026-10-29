<?php

namespace Modules\Class\Services;

use App\Models\Tenant;
use App\Models\User;
use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\HomeworkSubmission;

/**
 * A person's side of classes in the user panel (ADR 0026): the sessions of
 * the classes they are a member of (in any class role) or teach, in the
 * current tenant, laid out as a day, a Saturday-to-Friday week or a Jalali
 * month.
 */
class MyClasses
{
    public const VIEWS = ['day', 'week', 'month'];

    /**
     * @return Builder<ClassSession>
     */
    public function sessions(User $user): Builder
    {
        return ClassSession::query()->where(fn (Builder $query) => $query
            ->whereIn('classroom_id', ClassMember::query()->select('classroom_id')->where('user_id', $user->getKey()))
            ->orWhere('teacher_id', $user->getKey())
            ->orWhere('substitute_teacher_id', $user->getKey()));
    }

    public function hasAny(User $user): bool
    {
        return ClassMember::query()->where('user_id', $user->getKey())->exists()
            || ClassSession::query()->where(fn (Builder $query) => $query->where('teacher_id', $user->getKey())->orWhere('substitute_teacher_id', $user->getKey()))->exists();
    }

    public function canSee(User $user, ClassSession $session): bool
    {
        return $this->sessions($user)->whereKey($session->getKey())->exists();
    }

    /**
     * What the user is in the session: «مدرس» (teacher or substitute) or
     * their class roles there; whether attendance is taken for them.
     *
     * @return array{label: string, is_teacher: bool, is_attendee: bool}
     */
    public function roleIn(User $user, ClassSession $session): array
    {
        $isTeacher = in_array($user->getKey(), [$session->teacher_id, $session->substitute_teacher_id], true);
        $members = ClassMember::query()->where('classroom_id', $session->classroom_id)->where('user_id', $user->getKey())->with('classRole')->get();

        return [
            'label' => $isTeacher ? ($session->substitute_teacher_id === $user->getKey() ? 'مدرس جایگزین' : 'مدرس') : $members->pluck('classRole.title')->join('، '),
            'is_teacher' => $isTeacher,
            'is_attendee' => $members->contains(fn (ClassMember $member): bool => $member->classRole->is_attendee),
        ];
    }

    /**
     * The days of the view around the date, each with its sessions and holiday.
     *
     * @return array{view: string, date: string, label: string, previous: string, next: string, today: string, leading_blanks: int, days: list<array<string, mixed>>}
     */
    public function calendar(User $user, Tenant $tenant, string $view, CarbonImmutable $date): array
    {
        [$first, $last, $previous, $next, $label] = match ($view) {
            'day' => [$date, $date, $date->subDay(), $date->addDay(), self::jalali($date, 'l Y/m/d')],
            'month' => $this->month($date),
            default => $this->week($date),
        };

        $sessions = $this->sessions($user)
            ->whereBetween('starts_at', [$first->startOfDay(), $last->endOfDay()])
            ->with(['classroom', 'subject', 'teacher', 'substituteTeacher'])
            ->orderBy('starts_at')
            ->get();
        $attendance = Attendance::query()->where('user_id', $user->getKey())->whereIn('session_id', $sessions->modelKeys())->get()->keyBy('session_id');
        $submissions = HomeworkSubmission::query()->where('user_id', $user->getKey())->whereIn('session_id', $sessions->modelKeys())->get()->keyBy('session_id');
        $holidays = Holiday::applyingIn($tenant)->whereDate('starts_on', '<=', $last)->whereDate('ends_on', '>=', $first)->get();

        $days = [];
        for ($day = $first; $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'day_number' => (int) self::jalali($day, 'j'),
                'label' => self::jalali($day, 'l j F'),
                'is_today' => $day->isToday(),
                'holiday' => $holidays->first(fn (Holiday $holiday): bool => $day->betweenIncluded($holiday->starts_on, $holiday->ends_on))?->title,
                'sessions' => $sessions
                    ->filter(fn (ClassSession $session): bool => $session->starts_at->isSameDay($day))
                    ->map(fn (ClassSession $session): array => $this->sessionCard($session, $attendance->get($session->getKey()), $submissions->get($session->getKey())))
                    ->values()
                    ->all(),
            ];
        }

        return [
            'view' => $view,
            'date' => $date->toDateString(),
            'label' => $label,
            'previous' => $previous->toDateString(),
            'next' => $next->toDateString(),
            'today' => today()->toDateString(),
            'leading_blanks' => $view === 'month' ? Weekday::from($first->dayOfWeek)->position() : 0,
            'days' => $days,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionCard(ClassSession $session, ?Attendance $attendance = null, ?HomeworkSubmission $submission = null): array
    {
        return [
            'id' => $session->getKey(),
            'class' => $session->classroom->title,
            'subject' => $session->subject?->title,
            'time' => $session->starts_at->format('H:i').'–'.$session->ends_at->format('H:i'),
            'location' => $session->location,
            'meeting_url' => $session->classroom->mode->meetsOnline() ? $session->classroom->meeting_url : null,
            'teacher' => ($session->substituteTeacher ?? $session->teacher)?->name,
            'status' => $session->status->value,
            'status_label' => $session->status->getLabel(),
            'kind_label' => $session->kind->getLabel(),
            'has_homework' => $session->homework !== null,
            'my_attendance' => $attendance?->status->getLabel(),
            'my_homework' => match (true) {
                $session->homework === null || $submission === null => null,
                $submission->isGraded() && $submission->score !== null => 'نمره‌ی تکلیف: '.self::score($submission->score).' از '.($session->homework_max_score ?? config('class.homework.default_max_score')),
                default => 'تکلیف تحویل شد',
            },
        ];
    }

    /**
     * 18.50 → «18.5», 20.00 → «20».
     */
    public static function score(mixed $score): string
    {
        return rtrim(rtrim(number_format((float) $score, 2, '.', ''), '0'), '.');
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable, CarbonImmutable, CarbonImmutable, string}
     */
    private function week(CarbonImmutable $date): array
    {
        $first = $date->subDays(Weekday::from($date->dayOfWeek)->position());
        $last = $first->addDays(6);

        return [$first, $last, $first->subWeek(), $first->addWeek(), self::jalali($first, 'j F').' تا '.self::jalali($last, 'j F Y')];
    }

    /**
     * The Jalali month of the date.
     *
     * @return array{CarbonImmutable, CarbonImmutable, CarbonImmutable, CarbonImmutable, string}
     */
    private function month(CarbonImmutable $date): array
    {
        [$year, $month] = CalendarUtils::toJalali($date->year, $date->month, $date->day);
        $first = self::gregorian($year, $month, 1);
        $last = self::gregorian($year, $month, CalendarUtils::jalaliMonthLength($year, $month));
        [$previousYear, $previousMonth] = $month === 1 ? [$year - 1, 12] : [$year, $month - 1];

        return [$first, $last, self::gregorian($previousYear, $previousMonth, 1), $last->addDay(), self::jalali($first, 'F Y')];
    }

    private static function gregorian(int $year, int $month, int $day): CarbonImmutable
    {
        [$gy, $gm, $gd] = CalendarUtils::toGregorian($year, $month, $day);

        return CarbonImmutable::create($gy, $gm, $gd)->startOfDay();
    }

    public static function jalali(CarbonImmutable $date, string $format): string
    {
        return CalendarUtils::strftime($format, $date->toMutable());
    }
}
