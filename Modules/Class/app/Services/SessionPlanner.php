<?php

namespace Modules\Class\Services;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
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
 * refuses clashes (one of its teachers, the class or the place busy at the
 * same time; several teachers per schedule and session, ADR 0068) and builds its sessions up to its last day, skipping holidays.
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

        $teacherIds = self::ids($data['teacher_ids'] ?? []);
        $this->checkSchedule($schedule, $classroom);
        $this->checkTeachers($classroom, $teacherIds);
        $planned = $this->planned($schedule, $classroom);

        if ($planned->count() > self::MAX_SESSIONS) {
            throw new ClassException('این برنامه بیش از '.self::MAX_SESSIONS.' جلسه می‌سازد؛ بازه را کوتاه‌تر کنید.');
        }

        $this->checkClashes($planned, $classroom, $teacherIds, $this->locationOf($schedule, $classroom), exceptScheduleId: $schedule->getKey());

        return DB::transaction(function () use ($schedule, $teacherIds): ClassSchedule {
            $schedule->save();
            $schedule->teachers()->sync($teacherIds);
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
            $this->sessionsOf($schedule)->upcoming()->with('teachers')->get()
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
        $teacherIds = $schedule->teachers()->pluck('users.id')->all();

        foreach ($this->sessionsOf($schedule)->upcoming()->with('teachers')->get() as $session) {
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
                    'location' => $location,
                ]);
                $session->teachers()->sync($teacherIds);
            }

            $planned->forget($key);
        }

        foreach ($planned as $slot) {
            ClassSession::query()->create([
                'tenant_id' => $schedule->tenant_id,
                'classroom_id' => $schedule->classroom_id,
                'schedule_id' => $schedule->getKey(),
                'subject_id' => $schedule->subject_id,
                'starts_at' => $slot['start'],
                'ends_at' => $slot['end'],
                'location' => $location,
                'kind' => SessionKind::Regular,
                'status' => SessionStatus::Scheduled,
            ])->teachers()->attach($teacherIds);
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
        $teacherIds = self::ids($data['teacher_ids'] ?? []);
        $this->checkTeachers($classroom, $teacherIds);
        $location = filled($data['location'] ?? null) ? trim($data['location']) : ($classroom->mode->meetsInPerson() ? $classroom->location : null);

        $this->checkClashes(collect([['start' => $start, 'end' => $end]]), $classroom, $teacherIds, $location);

        return DB::transaction(function () use ($classroom, $data, $teacherIds, $start, $end, $location): ClassSession {
            $session = ClassSession::query()->create([
                'tenant_id' => $classroom->tenant_id,
                'classroom_id' => $classroom->getKey(),
                'subject_id' => $data['subject_id'] ?? null,
                'starts_at' => $start,
                'ends_at' => $end,
                'location' => $location,
                'kind' => $data['kind'],
                'status' => SessionStatus::Scheduled,
                'note' => $data['note'] ?? null,
            ]);
            $session->teachers()->attach($teacherIds);

            return $session;
        });
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
        $this->checkClashes(collect([['start' => $start, 'end' => $end]]), $session->classroom, $session->actualTeacherIds(), $location, exceptSessionId: $session->getKey());

        $session->update([
            'original_starts_at' => $session->original_starts_at ?? $session->starts_at,
            'starts_at' => $start,
            'ends_at' => $end,
            'location' => $location,
            'is_modified' => true,
            ...self::changedNow(),
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

        $session->update(['status' => SessionStatus::Cancelled, 'cancel_reason' => $reason, 'is_modified' => true, ...self::changedNow()]);

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

        $this->checkClashes(collect([['start' => $session->starts_at->toImmutable(), 'end' => $session->ends_at->toImmutable()]]), $session->classroom, $session->actualTeacherIds(), $session->location, exceptSessionId: $session->getKey());
        $session->update(['status' => SessionStatus::Scheduled, 'cancel_reason' => null, ...self::changedNow()]);

        return $session;
    }

    /**
     * Deletes a session that hasn't been held. Its schedule remembers the
     * day, or rebuilding would bring the session back (ADR 0066).
     *
     * @throws ClassException
     */
    public function deleteSession(ClassSession $session): void
    {
        if (! $session->canBeDeleted()) {
            throw new ClassException('جلسه‌ای که برگزار شده، در حال برگزاری است یا مطالبی برای آن ثبت شده حذف نمی‌شود.');
        }

        DB::transaction(function () use ($session): void {
            $schedule = ClassSchedule::withoutTenantScope()->lockForUpdate()->find($session->schedule_id);

            if ($schedule !== null) {
                $day = ($session->original_starts_at ?? $session->starts_at)->toDateString();
                $schedule->update(['skipped_dates' => array_values(array_unique([...($schedule->skipped_dates ?? []), $day]))]);
            }

            $session->delete();
        });
    }

    /**
     * The session's own teachers, each with an optional substitute who
     * teaches instead of them (ADR 0068). A new teacher must be a member of
     * the class who isn't an attendee; a substitute anyone in the tenant.
     * Changing who teaches keeps the schedule from rebuilding the session.
     *
     * @param  list<array{teacher_id: mixed, substitute_id?: mixed}>  $rows
     *
     * @throws ClassException
     */
    public function setTeachers(ClassSession $session, array $rows): ClassSession
    {
        $current = $session->teachers()->pluck('users.id')->all();
        $before = $session->load('teachers')->actualTeacherIds();
        $rows = collect($rows)
            ->filter(fn (array $row): bool => filled($row['teacher_id'] ?? null))
            ->map(fn (array $row): array => [
                'teacher_id' => (int) $row['teacher_id'],
                'substitute_id' => filled($row['substitute_id'] ?? null) && (int) $row['substitute_id'] !== (int) $row['teacher_id'] ? (int) $row['substitute_id'] : null,
            ])
            ->values();
        $teacherIds = $rows->pluck('teacher_id')->all();
        $actual = $rows->map(fn (array $row): int => $row['substitute_id'] ?? $row['teacher_id']);

        if (count($teacherIds) !== count(array_unique($teacherIds)) || $actual->count() !== $actual->unique()->count()) {
            throw new ClassException('هر نفر فقط یک بار در فهرست مدرس‌های جلسه می‌آید.');
        }

        $this->checkTeachers($session->classroom, array_values(array_diff($teacherIds, $current)));

        $substituteIds = $rows->pluck('substitute_id')->filter()->unique()->values();
        if ($substituteIds->isNotEmpty()) {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->findOrFail($session->tenant_id);

            if (User::query()->inTenant($tenant)->whereKey($substituteIds)->count() !== $substituteIds->count()) {
                throw new ClassException('مدرس جایگزین باید عضو همین '.config('sarasystem.tenant_label').' باشد.');
            }
        }

        if ($session->status === SessionStatus::Scheduled) {
            $this->checkClashes(collect([['start' => $session->starts_at->toImmutable(), 'end' => $session->ends_at->toImmutable()]]), null, array_values(array_diff($actual->all(), $before)), null, exceptSessionId: $session->getKey());
        }

        DB::transaction(function () use ($session, $rows, $teacherIds, $current): void {
            $session->teachers()->detach();
            foreach ($rows as $row) {
                $session->teachers()->attach($row['teacher_id'], ['substitute_id' => $row['substitute_id']]);
            }

            $session->update([
                ...(array_diff($teacherIds, $current) !== [] || array_diff($current, $teacherIds) !== [] ? ['is_modified' => true] : []),
                ...self::changedNow(),
            ]);
        });

        return $session->unsetRelation('teachers')->unsetRelation('substitutes');
    }

    /**
     * Who teaches instead of one of the session's teachers; null takes it back.
     *
     * @throws ClassException
     */
    public function setSubstitute(ClassSession $session, int $teacherId, ?int $substituteId): ClassSession
    {
        $rows = $session->teachers()->get()->map(fn (User $teacher): array => [
            'teacher_id' => $teacher->getKey(),
            'substitute_id' => $teacher->getKey() === $teacherId ? $substituteId : $teacher->pivot->substitute_id,
        ]);

        if (! $rows->contains('teacher_id', $teacherId)) {
            throw new ClassException('این مدرس در این جلسه نیست.');
        }

        return $this->setTeachers($session, $rows->all());
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
     * The schedule's future meetings, without holidays and the days whose
     * session was deleted by hand.
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
            ->reject(fn (CarbonImmutable $date): bool => in_array($date->toDateString(), $schedule->skipped_dates ?? [], true))
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

        if ($schedule->teaching_plan_id !== null && ! TeachingPlan::definedPlans()->whereKey($schedule->teaching_plan_id)->exists()) {
            throw new ClassException('طرح درس پیدا نشد.');
        }
    }

    /**
     * @param  list<int>  $teacherIds
     *
     * @throws ClassException
     */
    private function checkTeachers(Classroom $classroom, array $teacherIds): void
    {
        if ($teacherIds !== [] && array_diff($teacherIds, array_keys($this->teacherOptions($classroom))) !== []) {
            throw new ClassException('مدرس باید عضو کلاس (غیر از شرکت‌کنندگان) باشد.');
        }
    }

    /**
     * Refuses meetings that overlap a session of the same class, of one of
     * the teachers (in any tenant, as who actually teaches it) or in the
     * same place of the tenant.
     *
     * @param  Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $slots
     * @param  list<int>  $teacherIds
     *
     * @throws ClassException
     */
    private function checkClashes(Collection $slots, ?Classroom $classroom, array $teacherIds, ?string $location, ?int $exceptScheduleId = null, ?int $exceptSessionId = null): void
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
                ->when($teacherIds !== [], fn (Builder $query) => $query->orWhereExists(fn (QueryBuilder $row) => $row
                    ->from(ClassSession::TEACHERS_TABLE)
                    ->whereColumn(ClassSession::TEACHERS_TABLE.'.session_id', 'class_sessions.id')
                    ->whereIn(DB::raw('coalesce(substitute_id, teacher_id)'), $teacherIds)))
                ->when($classroom && filled($location), fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                    ->where('tenant_id', $classroom->tenant_id)
                    ->whereRaw('lower(location) = ?', [mb_strtolower($location)]))))
            ->with(['classroom' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)->with('tenant'), 'teachers'])
            ->get();

        foreach ($slots as $slot) {
            $clash = $others->first(fn (ClassSession $other): bool => $other->starts_at->lessThan($slot['end']) && $other->ends_at->greaterThan($slot['start']));

            if ($clash === null) {
                continue;
            }

            $busyTeacherId = array_values(array_intersect($teacherIds, $clash->actualTeacherIds()))[0] ?? null;
            $reason = match (true) {
                $classroom !== null && $clash->classroom_id === $classroom->getKey() => 'همین کلاس جلسه‌ی دیگری دارد',
                $busyTeacherId !== null => 'مدرس «'.User::query()->find($busyTeacherId)?->name.'» در «'.$this->classTitle($clash, $classroom).'» است',
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
     * Ids picked in a form, without blanks and repeats.
     *
     * @return list<int>
     */
    private static function ids(mixed $ids): array
    {
        return array_values(array_unique(array_map('intval', array_filter((array) $ids, 'filled'))));
    }

    /**
     * «8:00», «08:00» or «08:00:00» as «08:00:00».
     */
    private static function time(mixed $time): string
    {
        return CarbonImmutable::parse((string) $time)->format('H:i:s');
    }

    /**
     * Who changed the session's time, status or teachers, and when; only the
     * last one is kept (ADR 0072).
     *
     * @return array{changed_by_id: int|string|null, changed_at: Carbon}
     */
    private static function changedNow(): array
    {
        return ['changed_by_id' => auth()->id(), 'changed_at' => now()];
    }
}
