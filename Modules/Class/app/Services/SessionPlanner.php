<?php

namespace Modules\Class\Services;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\TeachingPlan;

/**
 * Schedules and their sessions (ADR 0023). Saving a schedule checks it,
 * refuses clashes (the teacher, the class or the place busy at the same
 * time) and builds its sessions up to its last day, skipping holidays.
 * Building again keeps past sessions and those changed by hand, updates
 * the untouched future ones and adds or removes the rest. Holidays
 * changing rebuild the schedules they touch.
 */
class SessionPlanner
{
    /** At most this many sessions per schedule. */
    public const MAX_SESSIONS = 1000;

    public function __construct(private Recurrence $recurrence) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ClassException
     */
    public function saveSchedule(?ClassSchedule $schedule, Classroom $classroom, array $data): ClassSchedule
    {
        $schedule ??= new ClassSchedule(['tenant_id' => $classroom->tenant_id, 'classroom_id' => $classroom->getKey()]);
        $schedule->fill([
            'subject_id' => $data['subject_id'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'teaching_plan_id' => $data['teaching_plan_id'] ?? null,
            'frequency' => $data['frequency'],
            'interval' => (int) ($data['interval'] ?? 1),
            'weekdays' => array_values(array_map(fn (mixed $day): int => $day instanceof Weekday ? $day->value : (int) $day, $data['weekdays'] ?? [])),
            'month_day' => $data['month_day'] ?? null,
            'month_week' => $data['month_week'] ?? null,
            'starts_on' => $data['starts_on'],
            'ends_on' => ($data['frequency'] instanceof Frequency ? $data['frequency'] : Frequency::from($data['frequency'])) === Frequency::Once ? $data['starts_on'] : $data['ends_on'],
            'start_time' => self::time($data['start_time']),
            'end_time' => self::time($data['end_time']),
            'location' => filled($data['location'] ?? null) ? trim($data['location']) : null,
        ]);

        $this->checkSchedule($schedule, $classroom);
        $planned = $this->planned($schedule, $classroom);

        if ($planned->count() > self::MAX_SESSIONS) {
            throw new ClassException('این برنامه بیش از '.self::MAX_SESSIONS.' جلسه می‌سازد؛ بازه را کوتاه‌تر کنید.');
        }

        $this->checkClashes($planned, $classroom, $schedule->teacher_id, $this->locationOf($schedule, $classroom), exceptScheduleId: $schedule->getKey());

        return DB::transaction(function () use ($schedule): ClassSchedule {
            $schedule->save();
            $this->sync($schedule);

            return $schedule;
        });
    }

    /**
     * Removes the schedule and its untouched future sessions; past and
     * changed ones stay without a schedule.
     */
    public function deleteSchedule(ClassSchedule $schedule): void
    {
        DB::transaction(function () use ($schedule): void {
            $this->sessionsOf($schedule)->upcoming()->get()
                ->filter(fn (ClassSession $session): bool => $session->isUntouched())
                ->each->delete();

            $schedule->delete();
        });
    }

    /**
     * Builds the schedule's future sessions again. No clash check: that is
     * done when the schedule is saved.
     */
    public function sync(ClassSchedule $schedule): void
    {
        /** @var Classroom $classroom */
        $classroom = Classroom::withoutTenantScope()->findOrFail($schedule->classroom_id);
        $planned = $this->planned($schedule, $classroom)->keyBy(fn (array $slot): string => $slot['start']->toDateString());
        $location = $this->locationOf($schedule, $classroom);

        foreach ($this->sessionsOf($schedule)->upcoming()->get() as $session) {
            $key = ($session->original_starts_at ?? $session->starts_at)->toDateString();

            if (! $planned->has($key)) {
                if ($session->isUntouched() || $session->status === SessionStatus::Cancelled) {
                    $session->delete();
                }

                continue;
            }

            if ($session->isUntouched()) {
                $session->update([
                    'starts_at' => $planned[$key]['start'],
                    'ends_at' => $planned[$key]['end'],
                    'subject_id' => $schedule->subject_id,
                    'teacher_id' => $schedule->teacher_id,
                    'location' => $location,
                ]);
            }

            $planned->forget($key);
        }

        foreach ($planned as $slot) {
            ClassSession::query()->create([
                'tenant_id' => $schedule->tenant_id,
                'classroom_id' => $schedule->classroom_id,
                'schedule_id' => $schedule->getKey(),
                'subject_id' => $schedule->subject_id,
                'teacher_id' => $schedule->teacher_id,
                'starts_at' => $slot['start'],
                'ends_at' => $slot['end'],
                'location' => $location,
                'kind' => SessionKind::Regular,
                'status' => SessionStatus::Scheduled,
            ]);
        }
    }

    /**
     * Rebuilds the schedules a holiday touches, in its tenant or (the
     * primary tenant's) in every tenant.
     */
    public function holidayChanged(Holiday $holiday): void
    {
        $dates = array_filter([
            $holiday->starts_on, $holiday->ends_on,
            $holiday->getOriginal('starts_on'), $holiday->getOriginal('ends_on'),
        ]);
        $from = min(array_map(fn (mixed $date): string => CarbonImmutable::parse($date)->toDateString(), $dates));
        $to = max(array_map(fn (mixed $date): string => CarbonImmutable::parse($date)->toDateString(), $dates));

        if ($to < today()->toDateString()) {
            return;
        }

        ClassSchedule::withoutTenantScope()
            ->when(! $holiday->isOrganizationWide(), fn (Builder $query) => $query->where('tenant_id', $holiday->tenant_id))
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', max($from, today()->toDateString()))
            ->get()
            ->each(fn (ClassSchedule $schedule) => $this->sync($schedule));
    }

    /**
     * A make-up or extra session added by hand.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ClassException
     */
    public function addSession(Classroom $classroom, array $data): ClassSession
    {
        [$start, $end] = self::interval($data['date'], $data['start_time'], $data['end_time']);
        $teacherId = filled($data['teacher_id'] ?? null) ? (int) $data['teacher_id'] : null;
        $this->checkTeacher($classroom, $teacherId);
        $location = filled($data['location'] ?? null) ? trim($data['location']) : ($classroom->mode->meetsInPerson() ? $classroom->location : null);

        $this->checkClashes(collect([['start' => $start, 'end' => $end]]), $classroom, $teacherId, $location);

        return ClassSession::query()->create([
            'tenant_id' => $classroom->tenant_id,
            'classroom_id' => $classroom->getKey(),
            'subject_id' => $data['subject_id'] ?? null,
            'teacher_id' => $teacherId,
            'starts_at' => $start,
            'ends_at' => $end,
            'location' => $location,
            'kind' => $data['kind'],
            'status' => SessionStatus::Scheduled,
            'note' => $data['note'] ?? null,
        ]);
    }

    /**
     * @throws ClassException
     */
    public function move(ClassSession $session, mixed $date, mixed $startTime, mixed $endTime, ?string $location): ClassSession
    {
        if ($session->hasStarted() || $session->status !== SessionStatus::Scheduled) {
            throw new ClassException('فقط جلسه‌ای که هنوز شروع نشده و لغو نشده جابه‌جا می‌شود.');
        }

        [$start, $end] = self::interval($date, $startTime, $endTime);

        if ($start->isPast()) {
            throw new ClassException('زمان تازه گذشته است.');
        }

        $location = filled($location) ? trim($location) : null;
        $this->checkClashes(collect([['start' => $start, 'end' => $end]]), $session->classroom, $session->actualTeacherId(), $location, exceptSessionId: $session->getKey());

        $session->update([
            'original_starts_at' => $session->original_starts_at ?? $session->starts_at,
            'starts_at' => $start,
            'ends_at' => $end,
            'location' => $location,
            'is_modified' => true,
        ]);

        return $session;
    }

    /**
     * @throws ClassException
     */
    public function cancel(ClassSession $session, string $reason): ClassSession
    {
        if ($session->status !== SessionStatus::Scheduled) {
            throw new ClassException('این جلسه برنامه‌ریزی‌شده نیست.');
        }

        $session->update(['status' => SessionStatus::Cancelled, 'cancel_reason' => $reason, 'is_modified' => true]);

        return $session;
    }

    /**
     * @throws ClassException
     */
    public function restore(ClassSession $session): ClassSession
    {
        if ($session->status !== SessionStatus::Cancelled) {
            throw new ClassException('این جلسه لغو نشده است.');
        }

        $this->checkClashes(collect([['start' => $session->starts_at->toImmutable(), 'end' => $session->ends_at->toImmutable()]]), $session->classroom, $session->actualTeacherId(), $session->location, exceptSessionId: $session->getKey());
        $session->update(['status' => SessionStatus::Scheduled, 'cancel_reason' => null]);

        return $session;
    }

    /**
     * Who teaches this session instead of its teacher; null takes it back.
     *
     * @throws ClassException
     */
    public function setSubstitute(ClassSession $session, ?int $userId): ClassSession
    {
        if ($userId !== null) {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->findOrFail($session->tenant_id);

            if (! User::query()->inTenant($tenant)->whereKey($userId)->exists()) {
                throw new ClassException('مدرس جایگزین باید عضو همین '.config('sarasystem.tenant_label').' باشد.');
            }

            if ($session->status === SessionStatus::Scheduled) {
                $this->checkClashes(collect([['start' => $session->starts_at->toImmutable(), 'end' => $session->ends_at->toImmutable()]]), null, $userId, null, exceptSessionId: $session->getKey());
            }
        }

        $session->update(['substitute_teacher_id' => $userId === $session->teacher_id ? null : $userId]);

        return $session;
    }

    /**
     * Current members of the class who are not attendees (teachers,
     * supervisors, …): who may teach its sessions.
     *
     * @return array<int, string>
     */
    public function teacherOptions(Classroom $classroom): array
    {
        return ClassMember::withoutTenantScope()
            ->where('classroom_id', $classroom->getKey())
            ->current()
            ->whereHas('classRole', fn (Builder $role) => $role->where('is_attendee', false))
            ->with(['user', 'classRole'])
            ->get()
            ->unique('user_id')
            ->mapWithKeys(fn (ClassMember $member): array => [$member->user_id => $member->user->name.' ('.$member->classRole->title.')'])
            ->all();
    }

    /**
     * The schedule's future meetings, without holidays.
     *
     * @return Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function planned(ClassSchedule $schedule, Classroom $classroom): Collection
    {
        $dates = $this->recurrence->dates($schedule);

        if ($dates === []) {
            return collect();
        }

        $holidays = Holiday::applyingIn(Tenant::query()->findOrFail($classroom->tenant_id))
            ->whereDate('starts_on', '<=', end($dates))
            ->whereDate('ends_on', '>=', $dates[0])
            ->get();

        return collect($dates)
            ->reject(fn (CarbonImmutable $date): bool => $holidays->contains(fn (Holiday $holiday): bool => $date->betweenIncluded($holiday->starts_on, $holiday->ends_on)))
            ->map(fn (CarbonImmutable $date): array => [
                'start' => $date->setTimeFromTimeString($schedule->start_time),
                'end' => $date->setTimeFromTimeString($schedule->end_time),
            ])
            ->filter(fn (array $slot): bool => $slot['start']->isFuture())
            ->values();
    }

    /**
     * @throws ClassException
     */
    private function checkSchedule(ClassSchedule $schedule, Classroom $classroom): void
    {
        if ($schedule->end_time <= $schedule->start_time) {
            throw new ClassException('ساعت پایان باید بعد از ساعت شروع باشد.');
        }

        if ($schedule->ends_on->lessThan($schedule->starts_on)) {
            throw new ClassException('تاریخ پایان برنامه پیش از تاریخ شروع آن است.');
        }

        if (($classroom->starts_on && $schedule->starts_on->lessThan($classroom->starts_on))
            || ($classroom->ends_on && $schedule->ends_on->greaterThan($classroom->ends_on))) {
            throw new ClassException('برنامه باید در بازه‌ی تاریخ کلاس باشد.');
        }

        $needsWeekdays = $schedule->frequency === Frequency::Weekly
            || ($schedule->frequency === Frequency::Monthly && $schedule->month_week !== null);
        if ($needsWeekdays && $schedule->weekdays === []) {
            throw new ClassException('روز هفته را انتخاب کنید.');
        }

        if ($schedule->frequency === Frequency::Monthly && $schedule->month_week === null && $schedule->month_day === null) {
            throw new ClassException('روز ماه را انتخاب کنید.');
        }

        $this->checkTeacher($classroom, $schedule->teacher_id);

        if ($schedule->teaching_plan_id !== null && ! TeachingPlan::definedPlans()->whereKey($schedule->teaching_plan_id)->exists()) {
            throw new ClassException('طرح درس پیدا نشد.');
        }
    }

    /**
     * @throws ClassException
     */
    private function checkTeacher(Classroom $classroom, ?int $teacherId): void
    {
        if ($teacherId !== null && ! array_key_exists($teacherId, $this->teacherOptions($classroom))) {
            throw new ClassException('مدرس باید عضو کلاس (غیر از شرکت‌کنندگان) باشد.');
        }
    }

    /**
     * Refuses meetings that overlap a session of the same class, of the
     * same teacher (in any tenant) or in the same place of the tenant.
     *
     * @param  Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $slots
     *
     * @throws ClassException
     */
    private function checkClashes(Collection $slots, ?Classroom $classroom, ?int $teacherId, ?string $location, ?int $exceptScheduleId = null, ?int $exceptSessionId = null): void
    {
        if ($slots->isEmpty()) {
            return;
        }

        $others = ClassSession::withoutTenantScope()
            ->takingPlace()
            ->where('starts_at', '<', $slots->max('end'))
            ->where('ends_at', '>', $slots->min('start'))
            ->when($exceptScheduleId, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('schedule_id')->orWhere('schedule_id', '!=', $exceptScheduleId)))
            ->when($exceptSessionId, fn (Builder $query) => $query->whereKeyNot($exceptSessionId))
            ->where(fn (Builder $query) => $query
                ->when($classroom, fn (Builder $query) => $query->orWhere('classroom_id', $classroom->getKey()))
                ->when($teacherId, fn (Builder $query) => $query
                    ->orWhere('substitute_teacher_id', $teacherId)
                    ->orWhere(fn (Builder $query) => $query->where('teacher_id', $teacherId)->whereNull('substitute_teacher_id')))
                ->when($classroom && filled($location), fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                    ->where('tenant_id', $classroom->tenant_id)
                    ->whereRaw('lower(location) = ?', [mb_strtolower($location)]))))
            ->with(['classroom' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)->with('tenant')])
            ->get();

        foreach ($slots as $slot) {
            $clash = $others->first(fn (ClassSession $other): bool => $other->starts_at->lessThan($slot['end']) && $other->ends_at->greaterThan($slot['start']));

            if ($clash === null) {
                continue;
            }

            $reason = match (true) {
                $classroom !== null && $clash->classroom_id === $classroom->getKey() => 'همین کلاس جلسه‌ی دیگری دارد',
                $teacherId !== null && $clash->actualTeacherId() === $teacherId => 'مدرس در «'.$this->classTitle($clash, $classroom).'» است',
                default => 'مکان در اختیار «'.$this->classTitle($clash, $classroom).'» است',
            };

            throw new ClassException("تداخل در {$this->moment($slot['start'])}: {$reason}.");
        }
    }

    /**
     * The clashing session's class, with its tenant when it is another one.
     */
    private function classTitle(ClassSession $clash, ?Classroom $classroom): string
    {
        $other = $clash->classroom;

        return $other->title.($other->tenant_id !== $classroom?->tenant_id ? ' - '.$other->tenant->name : '');
    }

    /**
     * @return Builder<ClassSession>
     */
    private function sessionsOf(ClassSchedule $schedule): Builder
    {
        return ClassSession::withoutTenantScope()->where('schedule_id', $schedule->getKey());
    }

    private function locationOf(ClassSchedule $schedule, Classroom $classroom): ?string
    {
        return $schedule->location ?? ($classroom->mode->meetsInPerson() ? $classroom->location : null);
    }

    private function moment(CarbonInterface $moment): string
    {
        return CalendarUtils::strftime('Y/m/d', $moment->toMutable()).' ساعت '.$moment->format('H:i');
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     *
     * @throws ClassException
     */
    private static function interval(mixed $date, mixed $startTime, mixed $endTime): array
    {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $start = $day->setTimeFromTimeString(self::time($startTime));
        $end = $day->setTimeFromTimeString(self::time($endTime));

        if ($end->lessThanOrEqualTo($start)) {
            throw new ClassException('ساعت پایان باید بعد از ساعت شروع باشد.');
        }

        return [$start, $end];
    }

    /**
     * «8:00», «08:00» or «08:00:00» as «08:00:00».
     */
    private static function time(mixed $time): string
    {
        return CarbonImmutable::parse((string) $time)->format('H:i:s');
    }
}
