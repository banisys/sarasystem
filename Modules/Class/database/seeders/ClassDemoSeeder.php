<?php

namespace Modules\Class\Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\GrantsDemoPermissions;
use Illuminate\Database\Seeder;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\Subject;
use Modules\Class\Models\Term;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\SessionPlanner;
use RuntimeException;

/**
 * Sample classes for local development (ADR 0022, ADR 0023), on top of the
 * core DemoSeeder: the school year 1405-1406 with the Nowruz holidays, four
 * class roles tied to the demo roles, four subjects, and in each branch two
 * classes with the branch's teachers, supporter, supervisor and students
 * (one class also has a sub-class), each with a weekly schedule and its
 * sessions. The demo roles get the classes permissions. Safe to run again.
 */
class ClassDemoSeeder extends Seeder
{
    use GrantsDemoPermissions;

    public function run(): void
    {
        $headOffice = Tenant::primary() ?? throw new RuntimeException('No primary tenant: run the core seeder first.');

        $this->grantDemoPermissions([
            'hq_member' => ['ViewAny:Classroom', 'View:Classroom', 'ViewAny:ClassSession',
                'ViewAny:Subject', 'View:Subject', 'Create:Subject', 'Update:Subject', 'Delete:Subject', 'Reorder:Subject',
                'ViewAny:Term', 'View:Term', 'Create:Term', 'Update:Term', 'Delete:Term',
                'ViewAny:ClassRole', 'View:ClassRole', 'Create:ClassRole', 'Update:ClassRole', 'Delete:ClassRole', 'Reorder:ClassRole',
                'ViewAny:Holiday', 'View:Holiday', 'Create:Holiday', 'Update:Holiday', 'Delete:Holiday'],
            'principal' => ['ViewAny:Classroom', 'View:Classroom', 'Create:Classroom', 'Update:Classroom', 'Delete:Classroom', 'ViewAny:ClassSession',
                'ViewAny:Holiday', 'View:Holiday', 'Create:Holiday', 'Update:Holiday', 'Delete:Holiday'],
        ]);

        [$term, $roles, $subjects] = $headOffice->execute(function (): array {
            // 1 Mehr 1405 – 31 Khordad 1406.
            $term = Term::query()->firstOrCreate(['title' => 'سال تحصیلی ۱۴۰۵-۱۴۰۶'], ['starts_on' => '2026-09-23', 'ends_on' => '2027-06-21', 'is_active' => true]);
            Holiday::query()->firstOrCreate(['title' => 'تعطیلات نوروز ۱۴۰۶'], ['starts_on' => '2027-03-21', 'ends_on' => '2027-04-02']);

            $roles = [
                'learner' => $this->role(config('sarasystem.learner_label'), ['student'], attendee: true, sort: 1),
                'teacher' => $this->role('مدرس', ['teacher'], takesAttendance: true, recordsContent: true, sort: 2),
                'supervisor' => $this->role('ناظر', ['supervisor', 'principal'], takesAttendance: true, sort: 3),
                'supporter' => $this->role('پشتیبان', ['supporter'], takesAttendance: true, sort: 4),
            ];

            $subjects = collect(['math' => 'ریاضی', 'science' => 'علوم تجربی', 'persian' => 'ادبیات فارسی', 'english' => 'زبان انگلیسی'])
                ->map(fn (string $title, string $key): Subject => Subject::query()->firstOrCreate(['title' => $title], ['sort' => array_search($key, ['math', 'science', 'persian', 'english'], true)]))
                ->all();

            return [$term, $roles, $subjects];
        });

        Tenant::query()->whereKeyNot($headOffice->getKey())->where('is_active', true)->get()
            ->each(fn (Tenant $branch) => $branch->execute(fn () => $this->seedBranch($branch, $term, $roles, $subjects)));
    }

    /**
     * @param  array<string, ClassRole>  $roles
     * @param  array<string, Subject>  $subjects
     */
    private function seedBranch(Tenant $branch, Term $term, array $roles, array $subjects): void
    {
        $people = fn (string $role): array => ClassRole::make(['allowed_role_ids' => [Role::query()->whereNull('tenant_id')->where('name', $role)->value('id')]])
            ->eligibleUsers($branch)->orderBy('id')->pluck('id')->all();
        $teachers = $people('teacher');
        $students = $people('student');

        if ($students === [] || $teachers === []) {
            return;
        }

        $main = $this->classroom('هفتم الف', $term, ClassMode::InPerson, capacity: 30, location: 'ساختمان اصلی، کلاس ۱۰۱');
        $this->addAll($main, $roles['learner'], $students);
        $this->addAll($main, $roles['teacher'], $teachers);
        $this->addAll($main, $roles['supervisor'], $people('supervisor'));
        $this->addAll($main, $roles['supporter'], $people('supporter'));

        $lab = $this->classroom('هفتم الف - گروه آزمایشگاه', $term, ClassMode::InPerson, location: 'آزمایشگاه', parent: $main);
        $this->addAll($lab, $roles['learner'], array_slice($students, 0, 2));
        $this->addAll($lab, $roles['teacher'], array_slice($teachers, 0, 1));

        $english = $this->classroom('زبان انگلیسی - مقدماتی', $term, ClassMode::Online, capacity: 15, meetingUrl: 'https://meet.example.com/english-'.$branch->slug);
        $this->addAll($english, $roles['learner'], $students);
        $this->addAll($english, $roles['teacher'], array_slice($teachers, -1));

        $this->schedule($main, $subjects['math'], $teachers[0], [Weekday::Saturday, Weekday::Monday, Weekday::Wednesday], '08:00', '09:30');
        $this->schedule($main, $subjects['science'], end($teachers), [Weekday::Sunday, Weekday::Tuesday], '10:00', '11:30');
        $this->schedule($lab, $subjects['science'], $teachers[0], [Weekday::Thursday], '09:00', '10:30', everyWeeks: 2);
        $this->schedule($english, $subjects['english'], end($teachers), [Weekday::Sunday, Weekday::Tuesday], '16:00', '17:30');
    }

    /**
     * A weekly schedule of the subject over the class's dates, unless the class has one.
     *
     * @param  list<Weekday>  $days
     */
    private function schedule(Classroom $classroom, Subject $subject, int $teacherId, array $days, string $from, string $to, int $everyWeeks = 1): void
    {
        if ($classroom->schedules()->where('subject_id', $subject->getKey())->exists()) {
            return;
        }

        try {
            app(SessionPlanner::class)->saveSchedule(null, $classroom, [
                'subject_id' => $subject->getKey(),
                'teacher_id' => $teacherId,
                'frequency' => Frequency::Weekly,
                'interval' => $everyWeeks,
                'weekdays' => array_map(fn (Weekday $day): int => $day->value, $days),
                'starts_on' => $classroom->starts_on->toDateString(),
                'ends_on' => $classroom->ends_on->toDateString(),
                'start_time' => $from,
                'end_time' => $to,
            ]);
        } catch (ClassException $exception) {
            // E.g. a demo teacher who also teaches in another branch at that time.
            $this->command?->warn("«{$classroom->title}»: {$exception->getMessage()}");
        }
    }

    /**
     * @param  list<string>  $systemRoles
     */
    private function role(string $title, array $systemRoles, bool $attendee = false, bool $takesAttendance = false, bool $recordsContent = false, int $sort = 0): ClassRole
    {
        return ClassRole::query()->updateOrCreate(['title' => $title], [
            'is_attendee' => $attendee,
            'takes_attendance' => $takesAttendance,
            'records_content' => $recordsContent,
            'allowed_role_ids' => Role::query()->whereNull('tenant_id')->whereIn('name', $systemRoles)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            'sort' => $sort,
        ]);
    }

    private function classroom(string $title, Term $term, ClassMode $mode, ?int $capacity = null, ?string $location = null, ?string $meetingUrl = null, ?Classroom $parent = null): Classroom
    {
        return Classroom::query()->updateOrCreate(['title' => $title], [
            'term_id' => $term->getKey(),
            'parent_id' => $parent?->getKey(),
            'mode' => $mode,
            'capacity' => $capacity,
            'location' => $location,
            'meeting_url' => $meetingUrl,
            'starts_on' => $term->starts_on,
            'ends_on' => $term->ends_on,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<int>  $userIds
     */
    private function addAll(Classroom $classroom, ClassRole $role, array $userIds): void
    {
        $new = array_values(array_diff($userIds, $classroom->members()->where('class_role_id', $role->getKey())->pluck('user_id')->all()));

        if ($new !== []) {
            app(ClassMembers::class)->add($classroom, $role, $new, CarbonImmutable::parse($classroom->starts_on));
        }
    }
}
