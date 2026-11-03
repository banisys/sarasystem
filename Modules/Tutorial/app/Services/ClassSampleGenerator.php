<?php

namespace Modules\Tutorial\Services;

use App\Authorization\DefaultRoles;
use App\Guardians\GuardianRelationship;
use App\Guardians\Guardians;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\HomeworkSubmission;
use Modules\Class\Models\Subject;
use Modules\Class\Models\TeachingPlan;
use Modules\Class\Models\Term;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\Recurrence;
use Modules\Class\Services\SessionPlanner;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Models\Employee;
use Modules\School\Services\Enrollments;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;
use Modules\Tutorial\Models\SampleBatch;
use Modules\Tutorial\Support\SampleTexts;
use Nwidart\Modules\Facades\Module;

/**
 * «ساخت داده‌ی نمونه» (ADR 0046): one or more classes with everything
 * around them, so a client sees the system full of life: accounts with
 * their roles (and learner / employee rows and guardians when those
 * modules are on), the classes and their sub-classes, members, subjects,
 * teaching plans, weekly timetables, past sessions with attendance,
 * content, homework, hand-ins and grades, and a cancelled session with its
 * make-up. Several classes share the subjects and plans, and may share
 * their staff (one teacher in every class); each class then meets at its
 * own time of the day, so a shared teacher has no clash.
 *
 * Members, schedules, cancellations and make-ups go through the class
 * services, so the class rules hold. The history of past sessions is
 * written directly: the services refuse past times, and taking attendance
 * through them would send absence SMS. Accounts have no mobile, so
 * nothing is ever sent to them. All in one transaction, recorded against
 * the batch (SampleRecorder).
 */
class ClassSampleGenerator
{
    /** Minutes between the day's sessions of two subjects. */
    private const int BREAK_MINUTES = 15;

    /** At most this many classes in one batch. */
    public const int MAX_CLASSES = 5;

    /** Suffixes of the classes' titles when there are several. */
    private const array CLASS_LETTERS = ['الف', 'ب', 'ج', 'د', 'ه'];

    /** Weekdays used for 1…6 sessions a week, Saturday first. */
    private const array WEEKDAY_ORDER = [6, 1, 3, 0, 2, 4];

    public function __construct(
        private SampleRecorder $recorder,
        private TutorialMode $mode,
        private ClassMembers $members,
        private SessionPlanner $planner,
        private Recurrence $recurrence,
        private Guardians $guardians,
    ) {}

    /**
     * What the form starts with; every option may be left out.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'tenant_id' => null,
            'title' => null,
            'mode' => ClassMode::InPerson->value,
            'capacity' => null,
            'term_id' => null,
            'grade_id' => null,
            'weeks_past' => 4,
            'weeks_ahead' => 8,
            'classes' => 1,
            // Staff (teachers, supporters, …) made once and put in every class.
            'shared_staff' => true,
            'sub_classes' => 0,
            // Class role id => how many people.
            'members' => [],
            'guardians' => 1,
            'password' => null,
            'subjects' => 1,
            'sessions_per_week' => 2,
            'start_time' => '16:00',
            'duration' => 90,
            'teaching_plan' => true,
            'topics' => 8,
            'attendance' => true,
            'absent_percent' => 10,
            'late_percent' => 5,
            'content' => true,
            'homework_percent' => 50,
            'submission_percent' => 70,
            'grade_homework' => true,
            'cancelled_sessions' => 1,
        ];
    }

    /**
     * How many of each class role by default: ten attendees and one
     * teacher (the first role that records content).
     *
     * @return array<int, int>
     */
    public static function defaultMembers(): array
    {
        $roles = ClassRole::definedRoles()->get();
        $teacher = $roles->first(fn (ClassRole $role): bool => ! $role->is_attendee && $role->records_content);

        return $roles->mapWithKeys(fn (ClassRole $role): array => [
            $role->getKey() => $role->is_attendee && $role->is($roles->firstWhere('is_attendee', true)) ? 10 : ($role->is($teacher) ? 1 : 0),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws ClassException
     */
    public function generate(array $options, User $owner): SampleBatch
    {
        $options = array_replace(self::defaults(), array_filter($options, fn (mixed $value): bool => $value !== null && $value !== ''));
        $tenant = Tenant::query()->find($options['tenant_id']) ?? throw new ClassException(config('sarasystem.tenant_label').' را انتخاب کنید.');
        $primary = Tenant::primary() ?? throw new ClassException('هنوز '.config('sarasystem.tenant_label').' اصلی تعیین نشده است.');

        if (array_sum(array_map('intval', $options['members'])) === 0) {
            throw new ClassException('دست‌کم برای یک نقش کلاس تعداد بگذارید.');
        }

        $options['classes'] = max(1, min(self::MAX_CLASSES, (int) $options['classes']));
        $lastEnd = $this->slotStart($options, $options['classes'] * self::lessonCount($options) - 1)->addMinutes((int) $options['duration']);
        if (! $lastEnd->isSameDay(today())) {
            throw new ClassException('جلسه‌های یک روز از نیمه‌شب می‌گذرند؛ ساعت شروع، مدت، تعداد درس‌ها یا تعداد کلاس‌ها را کم کنید.');
        }

        // Rows of a sample batch never belong to a tutorial run as well.
        return $this->mode->withoutRecording(fn (): SampleBatch => DB::transaction(function () use ($options, $tenant, $primary, $owner): SampleBatch {
            $batch = SampleBatch::query()->create(['code' => Str::random(20), 'title' => '', 'tenant_id' => $tenant->getKey(), 'user_id' => $owner->getKey(), 'options' => Arr::except($options, 'password')]);
            $code = sprintf('SMP-%04d', $batch->getKey());
            $batch->update(['code' => $code, 'title' => filled($options['title']) ? trim($options['title']) : 'کلاس نمونه '.$batch->getKey()]);

            $this->recorder->during($batch, fn () => $tenant->execute(fn () => $this->build($batch, $tenant, $primary, $options, $owner)));

            return $batch;
        }));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function build(SampleBatch $batch, Tenant $tenant, Tenant $primary, array $options, User $owner): void
    {
        $from = today()->toImmutable()->subWeeks((int) $options['weeks_past']);
        $to = today()->toImmutable()->addWeeks(max(1, (int) $options['weeks_ahead']));

        $term = filled($options['term_id'])
            ? Term::definedTerms()->findOrFail($options['term_id'])
            : Term::withoutTenantScope()->create(['tenant_id' => $primary->getKey(), 'title' => 'دوره‌ی نمونه ('.$batch->code.')', 'starts_on' => $from, 'ends_on' => $to, 'is_active' => true]);
        $lessons = $this->addLessons($batch, $primary, $options);
        $sharedStaff = [];

        for ($number = 0; $number < $options['classes']; $number++) {
            $classroom = $this->addClassroom($batch, $tenant, $term, $options, $number, $from, $to);

            if (filled($options['grade_id']) && self::enabled('School')) {
                app(Enrollments::class)->setClassroomGrade($classroom, (int) $options['grade_id'], null);
            }

            $people = $this->addMembers($classroom, $tenant, $options, $owner, $from, $sharedStaff);
            $this->addSubClasses($classroom, $people, (int) $options['sub_classes'], $from);

            $schedules = $this->addSchedules($classroom, $lessons, $options, $people['teachers'], $number * count($lessons), $from, $to);

            foreach ($schedules as $schedule) {
                $this->addHistory($classroom, $schedule, $tenant, $options, $people['attendees'], $owner, $from);
            }

            $this->cancelWithMakeUp($classroom, (int) $options['cancelled_sessions']);
        }
    }

    /**
     * The nth class: the batch's title and code, with a letter and a
     * number when there are several.
     *
     * @param  array<string, mixed>  $options
     */
    private function addClassroom(SampleBatch $batch, Tenant $tenant, Term $term, array $options, int $number, CarbonImmutable $from, CarbonImmutable $to): Classroom
    {
        $mode = $options['mode'] instanceof ClassMode ? $options['mode'] : ClassMode::from($options['mode']);
        $several = $options['classes'] > 1;
        $code = $several ? $batch->code.'-'.($number + 1) : $batch->code;

        return Classroom::withoutTenantScope()->create([
            'tenant_id' => $tenant->getKey(),
            'term_id' => $term->getKey(),
            'title' => $several ? $batch->title.' '.self::CLASS_LETTERS[$number] : $batch->title,
            'code' => $code,
            'description' => 'داده‌ی نمونه ('.$batch->code.') برای آشنایی با سامانه؛ با حذف این دسته در «داده‌ی نمونه» پاک می‌شود.',
            'mode' => $mode,
            'location' => $mode->meetsInPerson() ? 'اتاق '.$code : null,
            'meeting_url' => $mode === ClassMode::InPerson ? null : 'https://meet.example.com/'.Str::lower($code),
            'capacity' => filled($options['capacity']) ? (int) $options['capacity'] : null,
            'starts_on' => $from,
            'ends_on' => $to,
            'is_active' => true,
        ]);
    }

    /**
     * Accounts for each class role, added to the class. Attendees are
     * new for each class; with shared staff, the staff made for the first
     * class are put in the next ones too.
     *
     * @param  array<string, mixed>  $options
     * @param  array<int, list<int>>  $sharedStaff  class role id => user ids, filled by the first class
     * @return array{attendees: list<int>, attendeeRole: ?ClassRole, teachers: list<int>, staff: array<int, list<int>>}
     */
    private function addMembers(Classroom $classroom, Tenant $tenant, array $options, User $owner, CarbonImmutable $joinedOn, array &$sharedStaff): array
    {
        $people = ['attendees' => [], 'attendeeRole' => null, 'teachers' => [], 'staff' => []];
        $roles = ClassRole::definedRoles()->get()->keyBy('id');

        foreach ($options['members'] as $roleId => $count) {
            $role = $roles->get((int) $roleId);
            $count = (int) $count;

            if ($role === null || $count < 1) {
                continue;
            }

            $reuse = ! $role->is_attendee && $options['shared_staff'] && isset($sharedStaff[$role->getKey()]);
            $systemRole = $this->systemRoleFor($role);
            $userIds = $reuse ? $sharedStaff[$role->getKey()] : [];

            for ($i = 0; $i < ($reuse ? 0 : $count); $i++) {
                [$first, $last] = SampleTexts::person();
                $user = $this->makeUser("{$first} {$last}", $tenant, $systemRole, $options['password']);
                $userIds[] = $user->getKey();

                if ($role->is_attendee) {
                    $this->addLearnerRecord($user, $tenant, $classroom->code, count($people['attendees']) + $i + 1, $joinedOn);
                    $this->addGuardians($user, $last, $tenant, (int) $options['guardians'], $options['password'], $owner);
                } elseif (self::enabled('Employee')) {
                    Employee::withoutTenantScope()->create([
                        'tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'job_title' => $role->title,
                        'employment_type' => EmploymentType::PartTime, 'status' => EmploymentStatus::Active, 'hired_at' => $joinedOn,
                    ]);
                }
            }

            $this->members->add($classroom, $role, $userIds, $joinedOn);

            if ($role->is_attendee) {
                $people['attendees'] = [...$people['attendees'], ...$userIds];
                $people['attendeeRole'] ??= $role;
            } else {
                $people['staff'][$role->getKey()] = $userIds;
                $sharedStaff[$role->getKey()] ??= $userIds;
            }
        }

        // Who teaches the timetable: those who record content, else those
        // who take attendance, else any staff member.
        $staffRoles = $roles->only(array_keys($people['staff']));
        $teachingRole = $staffRoles->first(fn (ClassRole $role): bool => $role->records_content)
            ?? $staffRoles->first(fn (ClassRole $role): bool => $role->takes_attendance)
            ?? $staffRoles->first();
        $people['teachers'] = $teachingRole ? $people['staff'][$teachingRole->getKey()] : [];

        return $people;
    }

    /**
     * The system role a sample member of the class role gets: learner or
     * instructor when the class role allows it, else the first role it
     * allows, else learner (attendees) or staff.
     */
    private function systemRoleFor(ClassRole $role): Role
    {
        $preferred = Role::query()->whereNull('tenant_id')->whereIn('name', [DefaultRoles::LEARNER, DefaultRoles::INSTRUCTOR])->pluck('id')->all();
        $allowed = $role->allowed_role_ids;
        $roleId = $allowed === []
            ? null
            : (array_values(array_intersect($allowed, $preferred))[0] ?? $allowed[0]);

        $systemRole = $roleId !== null
            ? Role::query()->find($roleId)
            : Role::query()->whereNull('tenant_id')->where('name', $role->is_attendee ? DefaultRoles::LEARNER : DefaultRoles::STAFF)->first();

        return $systemRole ?? throw new ClassException("نقشی برای «{$role->title}» پیدا نشد؛ «php artisan roles:install-defaults» را اجرا کنید.");
    }

    private function makeUser(string $name, Tenant $tenant, Role $role, ?string $password): User
    {
        $user = User::query()->create(['name' => $name, 'national_code' => SampleTexts::nationalCode(), 'password' => $password ?? Str::random(32)]);
        $tenant->execute(fn () => $user->assignRole($role));
        $user->unsetRelation('roles');

        return $user;
    }

    private function addLearnerRecord(User $user, Tenant $tenant, string $code, int $number, CarbonImmutable $enrolledOn): void
    {
        if (! self::enabled('Student')) {
            return;
        }

        Student::withoutTenantScope()->create([
            'tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'student_code' => $code.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'status' => StudentStatus::Active, 'enrolled_at' => $enrolledOn,
        ]);
    }

    /**
     * The father (same last name), then the mother.
     */
    private function addGuardians(User $ward, string $lastName, Tenant $tenant, int $count, ?string $password, User $owner): void
    {
        $parents = [
            [GuardianRelationship::Father, SampleTexts::pick(SampleTexts::MALE_NAMES).' '.$lastName],
            [GuardianRelationship::Mother, implode(' ', SampleTexts::person(male: false))],
        ];

        foreach (array_slice($parents, 0, max(0, min(2, $count))) as [$relationship, $name]) {
            $guardian = User::query()->create(['name' => $name, 'national_code' => SampleTexts::nationalCode(), 'password' => $password ?? Str::random(32)]);
            $this->guardians->link($ward, $guardian, $relationship, receivesSms: false, tenant: $tenant, actor: $owner);
        }
    }

    /**
     * Sub-classes share out the class's attendees; its teachers teach each.
     *
     * @param  array{attendees: list<int>, attendeeRole: ?ClassRole, teachers: list<int>, staff: array<int, list<int>>}  $people
     */
    private function addSubClasses(Classroom $classroom, array $people, int $count, CarbonImmutable $joinedOn): void
    {
        $groups = $people['attendees'] === [] ? [] : array_chunk($people['attendees'], (int) ceil(count($people['attendees']) / max(1, $count)));
        $staffRoles = ClassRole::definedRoles()->whereKey(array_keys($people['staff']))->get();

        for ($i = 1; $i <= $count; $i++) {
            $child = Classroom::withoutTenantScope()->create([
                ...$classroom->only(['tenant_id', 'term_id', 'mode', 'location', 'meeting_url', 'starts_on', 'ends_on', 'is_active']),
                'parent_id' => $classroom->getKey(),
                'title' => $classroom->title.' - گروه '.$i,
                'code' => $classroom->code.'-'.$i,
            ]);

            if (($group = $groups[$i - 1] ?? []) !== [] && $people['attendeeRole'] !== null) {
                $this->members->add($child, $people['attendeeRole'], $group, $joinedOn);
            }

            foreach ($staffRoles as $role) {
                $this->members->add($child, $role, $people['staff'][$role->getKey()], $joinedOn);
            }
        }
    }

    /**
     * The subjects (none when none is asked for) and their teaching
     * plans, shared by the batch's classes.
     *
     * @param  array<string, mixed>  $options
     * @return list<array{subject: ?Subject, plan: ?TeachingPlan}>
     */
    private function addLessons(SampleBatch $batch, Tenant $primary, array $options): array
    {
        $subjectCount = min((int) $options['subjects'], count(SampleTexts::SUBJECTS));
        $lessons = [];

        for ($i = 0; $i < self::lessonCount($options); $i++) {
            $subject = $subjectCount > 0
                ? Subject::withoutTenantScope()->create(['tenant_id' => $primary->getKey(), 'title' => SampleTexts::SUBJECTS[$i].' ('.$batch->code.')', 'is_active' => true, 'sort' => 0])
                : null;
            $plan = $options['teaching_plan'] ? $this->addPlan($primary, $subject, $batch, max(1, (int) $options['topics'])) : null;
            $lessons[] = ['subject' => $subject, 'plan' => $plan];
        }

        return $lessons;
    }

    /**
     * A weekly schedule per lesson, each in its own time of the day from
     * the given slot on.
     *
     * @param  list<array{subject: ?Subject, plan: ?TeachingPlan}>  $lessons
     * @param  array<string, mixed>  $options
     * @param  list<int>  $teacherIds
     * @return list<ClassSchedule>
     */
    private function addSchedules(Classroom $classroom, array $lessons, array $options, array $teacherIds, int $firstSlot, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $weekdays = array_slice(self::WEEKDAY_ORDER, 0, max(1, min(6, (int) $options['sessions_per_week'])));
        $schedules = [];

        foreach ($lessons as $i => $lesson) {
            $start = $this->slotStart($options, $firstSlot + $i);

            $schedules[] = $this->planner->saveSchedule(null, $classroom, [
                'frequency' => Frequency::Weekly,
                'interval' => 1,
                'weekdays' => $weekdays,
                'starts_on' => $from->toDateString(),
                'ends_on' => $to->toDateString(),
                'start_time' => $start->format('H:i'),
                'end_time' => $start->addMinutes((int) $options['duration'])->format('H:i'),
                'subject_id' => $lesson['subject']?->getKey(),
                'teacher_id' => $teacherIds === [] ? null : $teacherIds[$i % count($teacherIds)],
                'teaching_plan_id' => $lesson['plan']?->getKey(),
            ]);
        }

        return $schedules;
    }

    private function addPlan(Tenant $primary, ?Subject $subject, SampleBatch $batch, int $topics): TeachingPlan
    {
        $title = $subject ? Str::before($subject->title, ' (') : $batch->title;
        $plan = TeachingPlan::withoutTenantScope()->create([
            'tenant_id' => $primary->getKey(), 'subject_id' => $subject?->getKey(), 'title' => 'طرح درس '.$title.' ('.$batch->code.')', 'is_active' => true,
        ]);

        for ($i = 1; $i <= $topics; $i++) {
            $plan->topics()->create(['sort' => $i, 'title' => "{$title} - مبحث {$i}", 'sessions' => 1]);
        }

        return $plan;
    }

    /**
     * The schedule's sessions before now (the planner only builds future
     * ones) and, for those that ended, attendance, content, homework,
     * hand-ins and grades.
     *
     * @param  array<string, mixed>  $options
     * @param  list<int>  $attendeeIds
     */
    private function addHistory(Classroom $classroom, ClassSchedule $schedule, Tenant $tenant, array $options, array $attendeeIds, User $owner, CarbonImmutable $from): void
    {
        $holidays = Holiday::applyingIn($tenant)->whereDate('ends_on', '>=', $from)->whereDate('starts_on', '<=', today())->get();
        $topicIds = $schedule->teaching_plan_id ? TeachingPlan::withoutTenantScope()->findOrFail($schedule->teaching_plan_id)->topics()->orderBy('sort')->pluck('id')->all() : [];
        $recorderId = $schedule->teacher_id ?? $owner->getKey();
        $topic = 0;

        foreach ($this->recurrence->dates($schedule) as $date) {
            $start = $date->setTimeFromTimeString($schedule->start_time);
            $end = $date->setTimeFromTimeString($schedule->end_time);

            if ($start->isFuture() || $holidays->contains(fn (Holiday $holiday): bool => $date->betweenIncluded($holiday->starts_on, $holiday->ends_on))) {
                continue;
            }

            $session = ClassSession::withoutTenantScope()->create([
                'tenant_id' => $classroom->tenant_id, 'classroom_id' => $classroom->getKey(), 'schedule_id' => $schedule->getKey(),
                'subject_id' => $schedule->subject_id, 'teacher_id' => $schedule->teacher_id,
                'starts_at' => $start, 'ends_at' => $end, 'location' => $schedule->location ?? $classroom->location,
                'kind' => SessionKind::Regular, 'status' => SessionStatus::Scheduled,
            ]);

            if ($end->isFuture()) {
                continue;
            }

            $absentIds = $options['attendance'] ? $this->takeAttendance($session, $attendeeIds, $options, $recorderId) : [];

            if (! $options['content']) {
                continue;
            }

            $hasHomework = self::chance((int) $options['homework_percent']);
            $session->update([
                'covered_topic_ids' => isset($topicIds[$topic]) ? [$topicIds[$topic++]] : null,
                'summary' => SampleTexts::pick(SampleTexts::SUMMARIES),
                'homework' => $hasHomework ? SampleTexts::pick(SampleTexts::HOMEWORK) : null,
                'homework_due_on' => $hasHomework ? $date->addDays(3) : null,
                'homework_max_score' => $hasHomework ? (int) config('class.homework.default_max_score') : null,
                'content_recorded_by_id' => $recorderId,
                'content_recorded_at' => $end,
                'status' => SessionStatus::Held,
            ]);

            if ($hasHomework) {
                $this->handIn($session, array_diff($attendeeIds, $absentIds), $options, $recorderId);
            }
        }
    }

    /**
     * @param  list<int>  $attendeeIds
     * @param  array<string, mixed>  $options
     * @return list<int> the absent
     */
    private function takeAttendance(ClassSession $session, array $attendeeIds, array $options, int $recorderId): array
    {
        $absent = [];

        foreach ($attendeeIds as $userId) {
            $roll = random_int(1, 100);
            $status = match (true) {
                $roll <= (int) $options['absent_percent'] => random_int(1, 3) === 1 ? AttendanceStatus::Excused : AttendanceStatus::Absent,
                $roll <= (int) $options['absent_percent'] + (int) $options['late_percent'] => AttendanceStatus::Late,
                default => AttendanceStatus::Present,
            };

            if (in_array($status, [AttendanceStatus::Absent, AttendanceStatus::Excused], true)) {
                $absent[] = $userId;
            }

            // Written directly: through AttendanceRegister guardians would get absence SMS.
            Attendance::withoutTenantScope()->create([
                'tenant_id' => $session->tenant_id, 'classroom_id' => $session->classroom_id, 'session_id' => $session->getKey(), 'user_id' => $userId,
                'status' => $status,
                'late_minutes' => $status === AttendanceStatus::Late ? random_int(5, 20) : null,
                'note' => $status === AttendanceStatus::Excused ? 'بیماری' : null,
                'recorded_by_id' => $recorderId,
                'recorded_at' => $session->starts_at->copy()->addMinutes(10),
                // Marked as told, so no absence SMS goes out for it later.
                'absence_notified_at' => $status === AttendanceStatus::Absent ? $session->ends_at : null,
            ]);
        }

        $session->update(['status' => SessionStatus::Held]);

        return $absent;
    }

    /**
     * @param  array<int>  $attendeeIds
     * @param  array<string, mixed>  $options
     */
    private function handIn(ClassSession $session, array $attendeeIds, array $options, int $recorderId): void
    {
        $max = (int) $session->homework_max_score;

        foreach ($attendeeIds as $userId) {
            if (! self::chance((int) $options['submission_percent'])) {
                continue;
            }

            $submittedAt = CarbonImmutable::parse($session->ends_at)->addHours(random_int(2, 60))->min(now()->subMinute());
            $graded = $options['grade_homework'];

            HomeworkSubmission::withoutTenantScope()->create([
                'tenant_id' => $session->tenant_id, 'classroom_id' => $session->classroom_id, 'session_id' => $session->getKey(), 'user_id' => $userId,
                'answer' => SampleTexts::pick(SampleTexts::ANSWERS),
                'submitted_at' => $submittedAt,
                'score' => $graded ? round(random_int((int) ($max * 2.4), $max * 4) / 4, 2) : null,
                'feedback' => $graded ? SampleTexts::pick(SampleTexts::FEEDBACK) : null,
                'graded_by_id' => $graded ? $recorderId : null,
                'graded_at' => $graded ? $submittedAt->addDay()->min(now()) : null,
            ]);
        }
    }

    /**
     * The next sessions are cancelled, each with a make-up on one of the
     * following days at the same time.
     */
    private function cancelWithMakeUp(Classroom $classroom, int $count): void
    {
        $sessions = ClassSession::withoutTenantScope()->where('classroom_id', $classroom->getKey())->upcoming()
            ->where('status', SessionStatus::Scheduled)->orderBy('starts_at')->limit(max(0, $count))->get();

        foreach ($sessions as $session) {
            $this->planner->cancel($session, 'مدرس در مرخصی است.');

            for ($day = 1; $day <= 7; $day++) {
                try {
                    $this->planner->addSession($classroom, [
                        'date' => $session->starts_at->copy()->addDays($day)->toDateString(),
                        'start_time' => $session->starts_at->format('H:i'),
                        'end_time' => $session->ends_at->format('H:i'),
                        'kind' => SessionKind::MakeUp,
                        'subject_id' => $session->subject_id,
                        'teacher_id' => $session->teacher_id,
                        'note' => 'جبرانی جلسه‌ی لغوشده',
                    ]);

                    break;
                } catch (ClassException) {
                    // Busy that day; try the next.
                }
            }
        }
    }

    /**
     * When the day's session of the nth subject starts.
     *
     * @param  array<string, mixed>  $options
     */
    private function slotStart(array $options, int $index): CarbonImmutable
    {
        return today()->toImmutable()->setTimeFromTimeString((string) $options['start_time'])
            ->addMinutes($index * ((int) $options['duration'] + self::BREAK_MINUTES));
    }

    /**
     * Schedules per class: one per subject, or one without a subject.
     *
     * @param  array<string, mixed>  $options
     */
    private static function lessonCount(array $options): int
    {
        return max(1, min((int) $options['subjects'], count(SampleTexts::SUBJECTS)));
    }

    private static function chance(int $percent): bool
    {
        return random_int(1, 100) <= $percent;
    }

    public static function enabled(string $module): bool
    {
        return Module::find($module)?->isEnabled() ?? false;
    }
}
