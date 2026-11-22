<?php

namespace Modules\Class\Services;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\HomeworkSubmission;
use Modules\Class\Models\Subject;
use Modules\Class\Services\Online\OnlineRooms;

/**
 * A person's classes and academic calendar in the user panel (ADR 0026,
 * ADR 0111, ADR 0117).
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
            ->orWhere(fn (Builder $query) => $query->withTeacher($user)));
    }

    public function hasAny(User $user): bool
    {
        return ClassMember::query()->where('user_id', $user->getKey())->exists()
            || ClassSession::query()->withTeacher($user)->exists();
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
        $isSubstitute = $session->teachers->contains(fn (User $teacher): bool => $teacher->pivot->substitute_id === $user->getKey());
        $isTeacher = $isSubstitute || $session->teachers->contains($user);
        $members = ClassMember::query()->where('classroom_id', $session->classroom_id)->where('user_id', $user->getKey())->with('classRole')->get();

        return [
            'label' => $isTeacher ? ($isSubstitute ? 'مدرس جایگزین' : 'مدرس') : $members->pluck('classRole.title')->join('، '),
            'is_teacher' => $isTeacher,
            'is_attendee' => $members->contains(fn (ClassMember $member): bool => $member->classRole->is_attendee),
        ];
    }

    /**
     * The days of the view around the date, each with its sessions and holiday.
     *
     * @return array{
     *     view: string, date: string, label: string, previous: string, next: string, today: string, leading_blanks: int,
     *     filters: array{classroom: ?int, subject: ?int, mode: ?string, subjects: list<array{value: int, label: string}>, modes: list<array{value: string, label: string}>},
     *     days: list<array<string, mixed>>
     * }
     */
    public function calendar(User $user, Tenant $tenant, string $view, CarbonImmutable $date, ?int $subjectId = null, ?ClassMode $mode = null, ?int $classroomId = null): array
    {
        ['first' => $first, 'last' => $last, 'previous' => $previous, 'next' => $next, 'label' => $label] = SessionCalendar::period($view, $date);
        $label = $view === 'day' ? self::jalali($date, 'l Y/m/d') : $label;

        $query = ClassSession::query()
            ->where(fn (Builder $query) => $query
                ->attendedBy($user)
                ->orWhere(fn (Builder $query) => $query->taughtBy($user)));
        $subjects = Subject::definedSubjects()->whereIn('id', (clone $query)->select('subject_id'))->pluck('title', 'id');
        $subjectId = $subjectId !== null && $subjects->has($subjectId) ? $subjectId : null;

        $sessions = $query
            ->whereBetween('starts_at', [$first->startOfDay(), $last->endOfDay()])
            ->when($classroomId !== null, fn (Builder $query) => $query->where('classroom_id', $classroomId))
            ->when($subjectId !== null, fn (Builder $query) => $query->where('subject_id', $subjectId))
            ->with(['classroom.onlineRooms.server', 'subject', 'teachers', 'substitutes'])
            ->orderBy('starts_at')
            ->get();

        if ($mode !== null) {
            $sessions = $sessions->filter(fn (ClassSession $session): bool => $session->deliveryMode() === $mode);
        }

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
            'filters' => [
                'classroom' => $classroomId,
                'subject' => $subjectId,
                'mode' => $mode?->value,
                'subjects' => $subjects->map(fn (string $label, int $value): array => ['value' => $value, 'label' => $label])->values()->all(),
                'modes' => array_map(fn (ClassMode $mode): array => ['value' => $mode->value, 'label' => $mode->getLabel()], ClassMode::cases()),
            ],
            'days' => $days,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionCard(ClassSession $session, ?Attendance $attendance = null, ?HomeworkSubmission $submission = null): array
    {
        $tone = SessionCalendar::tone($session);

        return [
            'id' => $session->getKey(),
            'class' => $session->classroom->title,
            'title' => $session->title,
            'subject' => $session->subject?->title,
            'time' => $session->starts_at->format('H:i').'–'.$session->ends_at->format('H:i'),
            'location' => $session->physicalLocation(),
            'mode_label' => $session->deliveryMode()->getLabel(),
            // The platform it meets on online (ADR 0073); the way in is
            // added for the person themselves (MyClassController).
            'online_platform' => app(OnlineRooms::class)->forSession($session)?->platformName(),
            'teacher' => $session->teacherNames(),
            'status' => $session->status->value,
            'status_label' => $session->status->getLabel(),
            'kind_label' => $session->kind->getLabel(),
            'tone' => $tone,
            'tone_label' => SessionCalendar::toneLabels()[$tone],
            'is_live' => $tone === 'live',
            'is_exam' => $session->kind === SessionKind::Exam,
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

    public static function jalali(CarbonImmutable $date, string $format): string
    {
        return SessionCalendar::jalali($date, $format);
    }
}
