<?php

namespace Modules\School\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Database\Seeders\ClassDemoSeeder;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\Classrooms\Pages\ListClassrooms;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Subject;
use Modules\Class\Models\Term;
use Modules\Class\Services\ClassMembers;
use Modules\School\Database\Seeders\SchoolDemoSeeder;
use Modules\School\Enums\ScoreScale;
use Modules\School\Filament\Resources\Enrollments\EnrollmentResource;
use Modules\School\Filament\Resources\Enrollments\Pages\ListEnrollments;
use Modules\School\Filament\Resources\Grades\GradeResource;
use Modules\School\Filament\Resources\Grades\Pages\ViewGrade;
use Modules\School\Filament\Resources\Grades\RelationManagers\CurriculaRelationManager;
use Modules\School\Filament\Resources\Majors\MajorResource;
use Modules\School\Filament\Resources\Stages\StageResource;
use Modules\School\Models\ClassroomGrade;
use Modules\School\Models\Enrollment;
use Modules\School\Models\Grade;
use Modules\School\Models\Major;
use Modules\School\Models\Stage;
use Modules\School\Services\Enrollments;
use Modules\School\Services\SchoolException;
use Modules\Student\Filament\Resources\Students\StudentResource;
use Modules\Student\Models\Student;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Stages, grades, majors and curricula; classes' grades; learners' records
 * per academic year and promotion (ADR 0031).
 */
class SchoolStructureTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private User $admin;

    private Term $year;

    private Term $nextYear;

    private Grade $sixth;

    private Grade $seventh;

    private Grade $tenth;

    private Grade $twelfth;

    private Major $math;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->makeSuperAdmin($this->primary);

        $this->year = Term::factory()->for($this->primary)->create(['title' => '۱۴۰۵-۱۴۰۶', 'starts_on' => '2026-09-23', 'ends_on' => '2027-06-21']);
        $this->nextYear = Term::factory()->for($this->primary)->create(['title' => '۱۴۰۶-۱۴۰۷', 'starts_on' => '2027-09-23', 'ends_on' => '2028-06-21']);

        $this->primary->execute(function (): void {
            $primary = Stage::query()->create(['title' => 'دبستان', 'scale' => ScoreScale::Descriptive, 'sort' => 1]);
            $lower = Stage::query()->create(['title' => 'متوسطه اول', 'scale' => ScoreScale::Numeric, 'sort' => 2]);
            $upper = Stage::query()->create(['title' => 'متوسطه دوم', 'scale' => ScoreScale::Numeric, 'sort' => 3]);
            // Made out of order: the stage order decides.
            $this->tenth = Grade::query()->create(['stage_id' => $upper->id, 'title' => 'دهم', 'has_majors' => true, 'sort' => 1]);
            $this->twelfth = Grade::query()->create(['stage_id' => $upper->id, 'title' => 'دوازدهم', 'has_majors' => true, 'sort' => 3]);
            $this->sixth = Grade::query()->create(['stage_id' => $primary->id, 'title' => 'ششم', 'sort' => 6]);
            $this->seventh = Grade::query()->create(['stage_id' => $lower->id, 'title' => 'هفتم', 'sort' => 1]);
            $this->math = Major::query()->create(['title' => 'ریاضی و فیزیک']);
        });
    }

    public function test_the_structure_is_defined_in_the_primary_tenant_only(): void
    {
        $this->roleWithPermissions('hq', ['ViewAny:Stage', 'ViewAny:Grade', 'ViewAny:Major']);
        $clerk = User::factory()->create();
        foreach ([$this->primary, $this->west] as $tenant) {
            $this->makeStaff($clerk, $tenant);
            $this->grantRole($clerk, $tenant, 'hq');
        }

        $this->actingAs($clerk);
        foreach ([StageResource::class, GradeResource::class, MajorResource::class] as $resource) {
            $this->get($resource::getUrl('index', tenant: $this->primary))->assertOk();
            $this->get($resource::getUrl('index', tenant: $this->west))->assertForbidden();
        }
    }

    public function test_grades_move_up_in_stage_order(): void
    {
        $this->assertSame(['ششم', 'هفتم', 'دهم', 'دوازدهم'], Grade::definedGrades()->pluck('title')->all());
        $this->assertTrue($this->sixth->next()->is($this->seventh));
        $this->assertTrue($this->seventh->next()->is($this->tenth));
        $this->assertNull($this->twelfth->next());
    }

    public function test_a_grades_curriculum_has_each_subject_once_per_major(): void
    {
        $math = Subject::factory()->for($this->primary)->create(['title' => 'ریاضی']);
        $owner = ['ownerRecord' => $this->tenth, 'pageClass' => ViewGrade::class];

        $this->actingInPanel($this->admin, $this->primary);
        $add = fn (array $data) => Livewire::test(CurriculaRelationManager::class, $owner)->callAction(TestAction::make('create')->table(), data: $data);

        $add(['subject_id' => $math->id, 'major_id' => $this->math->id, 'coefficient' => 4, 'weekly_hours' => 6])->assertHasNoActionErrors();
        $add(['subject_id' => $math->id, 'major_id' => $this->math->id, 'coefficient' => 3])->assertHasActionErrors(['subject_id']);
        $add(['subject_id' => $math->id, 'coefficient' => 2])->assertHasNoActionErrors();

        $this->assertSame(['4.00', '2.00'], $this->tenth->curricula()->pluck('coefficient')->all());
    }

    public function test_a_class_with_a_grade_gives_its_learners_that_grade_for_the_year(): void
    {
        $learnerRole = ClassRole::factory()->for($this->primary)->attendee()->create();
        $this->roleWithPermissions('student', []);
        [$amir, $sara] = [$this->learner(), $this->learner()];

        $this->actingInPanel($this->admin, $this->west);
        Livewire::test(ListClassrooms::class)
            ->callAction('create', data: ['title' => 'هفتم الف', 'term_id' => $this->year->id, 'mode' => ClassMode::InPerson->value, 'school_grade_id' => $this->seventh->id])
            ->assertHasNoActionErrors();

        $classroom = Classroom::query()->where('title', 'هفتم الف')->sole();
        $this->assertSame($this->seventh->id, ClassroomGrade::query()->where('classroom_id', $classroom->id)->sole()->grade_id);

        app(ClassMembers::class)->add($classroom, $learnerRole, [$amir->id, $sara->id]);
        $record = Enrollment::query()->where('user_id', $amir->id)->sole();
        $this->assertSame([$this->year->id, $this->seventh->id, $classroom->id], [$record->term_id, $record->grade_id, $record->classroom_id]);

        // A grade with majors needs one; changing the grade moves the records.
        $this->assertRefused(fn () => app(Enrollments::class)->setClassroomGrade($classroom, $this->tenth->id, null), 'رشته');
        app(Enrollments::class)->setClassroomGrade($classroom, $this->tenth->id, $this->math->id);
        $this->assertSame([$this->tenth->id, $this->math->id], [$record->fresh()->grade_id, $record->fresh()->major_id]);

        $this->get(ClassroomResource::getUrl('view', ['record' => $classroom], tenant: $this->west))->assertOk()->assertSee('دهم - ریاضی و فیزیک');
    }

    public function test_promotion_makes_next_years_records_one_grade_up(): void
    {
        $this->roleWithPermissions('student', []);
        [$sixthGrader, $tenthGrader, $twelfthGrader, $alreadyEnrolled] = [$this->learner(), $this->learner(), $this->learner(), $this->learner()];
        $this->west->execute(function () use ($sixthGrader, $tenthGrader, $twelfthGrader, $alreadyEnrolled): void {
            $enrollments = app(Enrollments::class);
            $enrollments->save(null, $sixthGrader, $this->year->id, $this->sixth->id, null, null, $this->west->id);
            $enrollments->save(null, $tenthGrader, $this->year->id, $this->tenth->id, $this->math->id, null, $this->west->id);
            $enrollments->save(null, $twelfthGrader, $this->year->id, $this->twelfth->id, $this->math->id, null, $this->west->id);
            $enrollments->save(null, $alreadyEnrolled, $this->year->id, $this->sixth->id, null, null, $this->west->id);
            $enrollments->save(null, $alreadyEnrolled, $this->nextYear->id, $this->sixth->id, null, null, $this->west->id);

            $this->assertRefused(fn () => $enrollments->save(null, $sixthGrader, $this->year->id, $this->seventh->id, null, null, $this->west->id), 'پرونده دارد');
        });

        $this->actingInPanel($this->admin, $this->west);
        Livewire::test(ListEnrollments::class)
            ->callTableBulkAction('promote', Enrollment::query()->where('term_id', $this->year->id)->get(), data: ['term_id' => $this->nextYear->id])
            ->assertNotified('ارتقا: 2 نفر؛ دانش‌آموخته: 1؛ از پیش پرونده داشتند: 1');

        $next = Enrollment::query()->where('term_id', $this->nextYear->id)->get()->keyBy('user_id');
        $this->assertSame($this->seventh->id, $next[$sixthGrader->id]->grade_id);
        $this->assertNull($next[$sixthGrader->id]->major_id);
        $this->assertSame([$this->twelfth->id, $this->math->id], [$next[$tenthGrader->id]->grade_id, $next[$tenthGrader->id]->major_id]);
        $this->assertFalse($next->has($twelfthGrader->id));
        $this->assertSame($this->sixth->id, $next[$alreadyEnrolled->id]->grade_id);
    }

    public function test_another_tenants_records_are_invisible_and_the_learner_page_lists_their_own(): void
    {
        $this->roleWithPermissions('student', []);
        $mine = $this->learner();
        $theirs = User::factory()->create();
        app(Enrollments::class)->save(null, $mine, $this->year->id, $this->seventh->id, null, null, $this->west->id);
        app(Enrollments::class)->save(null, $theirs, $this->year->id, $this->seventh->id, null, null, $this->primary->id);
        $student = Student::factory()->for($this->west)->create(['user_id' => $mine->id]);

        $this->actingInPanel($this->admin, $this->west);
        Livewire::test(ListEnrollments::class)
            ->assertCanSeeTableRecords(Enrollment::withoutTenantScope()->where('user_id', $mine->id)->get())
            ->assertCanNotSeeTableRecords(Enrollment::withoutTenantScope()->where('user_id', $theirs->id)->get());

        $this->get(EnrollmentResource::getUrl('index', tenant: $this->west))->assertOk();
        $this->get(StudentResource::getUrl('view', ['record' => $student], tenant: $this->west))->assertOk()->assertSee('پرونده‌ی تحصیلی');
    }

    public function test_the_demo_seeder_sets_up_an_iranian_school(): void
    {
        $this->seed(DemoSeeder::class);
        Tenant::query()->where('slug', 'head-office')->sole()->makePrimary();
        $this->seed([ClassDemoSeeder::class, SchoolDemoSeeder::class, SchoolDemoSeeder::class]);

        $north = Tenant::query()->where('slug', 'north')->sole();
        $seventh = Grade::definedGrades()->where('school_grades.title', 'هفتم')->sole();
        $this->assertSame(4, $seventh->curricula()->count());
        $this->assertSame(3, Enrollment::withoutTenantScope()->whereBelongsTo($north)->where('grade_id', $seventh->id)->count());
    }

    private function learner(): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->west, 'student');

        return $user;
    }

    private function assertRefused(callable $operation, string $message): void
    {
        try {
            $operation();
        } catch (SchoolException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());

            return;
        }

        $this->fail("No error «{$message}».");
    }
}
