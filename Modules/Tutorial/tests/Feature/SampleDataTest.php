<?php

namespace Modules\Tutorial\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\HomeworkSubmission;
use Modules\Class\Models\Subject;
use Modules\Class\Models\Term;
use Modules\Class\Services\DefaultClassRoles;
use Modules\Employee\Models\Employee;
use Modules\Student\Models\Student;
use Modules\Tutorial\Filament\Resources\SampleBatches\Pages\ListSampleBatches;
use Modules\Tutorial\Filament\Resources\SampleBatches\SampleBatchResource;
use Modules\Tutorial\Models\SampleBatch;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Tests\Concerns\RunsTutorials;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SampleDataTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase, RunsTutorials;

    private Tenant $primary;

    private Tenant $branch;

    private User $owner;

    /** @var array<string, ClassRole> */
    private array $roles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->branch = Tenant::factory()->create(['name' => 'شعبه ونک']);
        app(DefaultRoles::class)->install();
        app(DefaultClassRoles::class)->install();
        $this->roleWithPermissions(Utils::getPanelUserRoleName(), []);
        $this->owner = User::factory()->superAdmin()->create();
        $this->actingInPanel($this->owner, $this->primary);

        $roles = ClassRole::definedRoles()->get();
        $this->roles = [
            'learner' => $roles->firstWhere('is_attendee', true),
            'teacher' => $roles->firstWhere('records_content', true),
            'supporter' => $roles->firstWhere('title', 'پشتیبان'),
        ];
    }

    public function test_it_makes_a_class_with_everything_around_it_and_deletes_all_of_it(): void
    {
        $before = ['users' => User::query()->count(), 'roles' => DB::table('model_has_roles')->count()];

        $this->generate(['members' => [$this->roles['learner']->id => 10, $this->roles['teacher']->id => 1, $this->roles['supporter']->id => 1], 'sub_classes' => 2, 'guardians' => 2]);

        $batch = SampleBatch::query()->sole();
        $this->assertSame(sprintf('SMP-%04d', $batch->id), $batch->code);
        $this->assertArrayNotHasKey('password', $batch->options);

        $classroom = $batch->made(Classroom::class)->whereNull('parent_id')->sole();
        $this->assertSame($this->branch->id, $classroom->tenant_id);
        $this->assertSame($batch->code, $classroom->code);
        $this->assertSame(2, $batch->made(Classroom::class)->where('parent_id', $classroom->id)->count());
        $this->assertSame(10, ClassMember::withoutTenantScope()->where('classroom_id', $classroom->id)->where('class_role_id', $this->roles['learner']->id)->count());
        $this->assertSame(1, ClassMember::withoutTenantScope()->where('classroom_id', $classroom->id)->where('class_role_id', $this->roles['supporter']->id)->count());
        // 12 members and two parents for each of the 10 learners.
        $this->assertSame(32, $batch->made(User::class)->count());
        $this->assertSame(20, $batch->made(Guardianship::class)->count());
        $this->assertSame(10, $batch->made(Student::class)->where('tenant_id', $this->branch->id)->count());
        $this->assertSame(2, $batch->made(Employee::class)->count());
        $this->assertTrue($batch->made(User::class)->whereNotNull('mobile')->doesntExist(), 'Sample accounts get no SMS.');

        $sessions = $batch->made(ClassSession::class)->get();
        $past = $sessions->filter(fn (ClassSession $session): bool => $session->ends_at->isPast());
        $this->assertGreaterThan(0, $past->count());
        $this->assertTrue($past->every(fn (ClassSession $session): bool => $session->status === SessionStatus::Held && filled($session->summary)));
        $this->assertSame($past->count() * 10, $batch->made(Attendance::class)->count());
        $this->assertTrue($sessions->contains(fn (ClassSession $session): bool => $session->starts_at->isFuture() && $session->status === SessionStatus::Scheduled));
        $this->assertSame(1, $sessions->where('status', SessionStatus::Cancelled)->count());
        $this->assertSame(1, $sessions->where('kind', SessionKind::MakeUp)->count());

        // Homework every past session: hand-ins, all graded.
        $this->assertGreaterThan(0, $batch->made(HomeworkSubmission::class)->count());
        $this->assertTrue($batch->made(HomeworkSubmission::class)->whereNull('score')->doesntExist());

        Livewire::test(ListSampleBatches::class)
            ->callAction(TestAction::make('erase')->table($batch))
            ->assertNotified('داده‌ها پاک شد');

        $this->assertModelMissing($batch);
        $this->assertModelMissing($classroom);
        $this->assertSame(0, Term::withoutTenantScope()->count());
        $this->assertSame(0, ClassSession::withoutTenantScope()->count());
        $this->assertSame($before, ['users' => User::query()->count(), 'roles' => DB::table('model_has_roles')->count()]);
        $this->assertModelExists($this->owner);
    }

    public function test_several_classes_share_one_teacher_at_different_times(): void
    {
        $this->generate([
            'classes' => 3, 'shared_staff' => true, 'subjects' => 2, 'start_time' => '08:00', 'cancelled_sessions' => 0,
            'members' => [$this->roles['learner']->id => 4, $this->roles['teacher']->id => 1],
        ]);

        $batch = SampleBatch::query()->sole();
        $classrooms = $batch->made(Classroom::class)->orderBy('id')->get();
        $this->assertSame(["{$batch->title} الف", "{$batch->title} ب", "{$batch->title} ج"], $classrooms->pluck('title')->all());
        $this->assertSame(["{$batch->code}-1", "{$batch->code}-2", "{$batch->code}-3"], $classrooms->pluck('code')->all());

        $teacherIds = ClassMember::withoutTenantScope()->where('class_role_id', $this->roles['teacher']->id)->pluck('user_id');
        $this->assertCount(3, $teacherIds);
        $this->assertCount(1, $teacherIds->unique());
        $this->assertSame(12, ClassMember::withoutTenantScope()->where('class_role_id', $this->roles['learner']->id)->distinct()->count('user_id'));
        // One term, two subjects with their plans, for all three classes.
        $this->assertSame(1, $batch->made(Term::class)->count());
        $this->assertSame(2, $batch->made(Subject::class)->count());
        $this->assertSame(6, $batch->made(ClassSchedule::class)->count());

        $sessions = $batch->made(ClassSession::class)->whereHas('teachers', fn ($teachers) => $teachers->whereKey($teacherIds->first()))->orderBy('starts_at')->get();
        $sessions->each(function (ClassSession $session, int $index) use ($sessions): void {
            $next = $sessions->get($index + 1);
            $this->assertTrue($next === null || $next->starts_at->greaterThanOrEqualTo($session->ends_at), 'The shared teacher never teaches two sessions at once.');
        });

        $this->actingAs($this->owner)
            ->get(ClassroomResource::getUrl('index', ['tableFilters' => ['title' => ['value' => $batch->title]]], panel: 'admin', tenant: $this->branch))
            ->assertOk()
            ->assertSee("{$batch->title} ب");

        // A new session for the teacher: `auth.session` remembers the last user.
        $this->flushSession();
        $this->actingAs(User::query()->find($teacherIds->first()))
            ->withSession([ResolveCurrentTenant::SESSION_KEY => $this->branch->id])
            ->get(route('my-classes.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('classes', 3));
    }

    public function test_several_classes_without_shared_staff_get_their_own_teachers(): void
    {
        $this->generate([
            'classes' => 2, 'shared_staff' => false,
            'members' => [$this->roles['learner']->id => 2, $this->roles['teacher']->id => 1],
        ]);

        $this->assertSame(2, ClassMember::withoutTenantScope()->where('class_role_id', $this->roles['teacher']->id)->distinct()->count('user_id'));
    }

    public function test_classes_that_would_meet_past_midnight_are_refused(): void
    {
        $this->generate(['classes' => 5, 'subjects' => 5, 'start_time' => '08:00', 'duration' => 60, 'members' => [$this->roles['learner']->id => 1]], expectSuccess: false);

        $this->assertSame(0, SampleBatch::query()->count());
    }

    public function test_a_failure_makes_nothing(): void
    {
        $this->generate(['capacity' => 5, 'members' => [$this->roles['learner']->id => 10]], expectSuccess: false);

        $this->assertSame(0, SampleBatch::query()->count());
        $this->assertSame(0, Classroom::withoutTenantScope()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_an_online_class_without_history_or_plan(): void
    {
        $this->generate([
            'mode' => ClassMode::Online->value, 'weeks_past' => 0, 'subjects' => 0, 'teaching_plan' => false,
            'attendance' => false, 'content' => false, 'cancelled_sessions' => 0, 'guardians' => 0,
            'members' => [$this->roles['learner']->id => 3, $this->roles['teacher']->id => 1],
        ]);

        $batch = SampleBatch::query()->sole();
        $classroom = $batch->made(Classroom::class)->sole();
        $this->assertNull($classroom->location);
        $this->assertNotNull($classroom->meeting_url);
        $this->assertSame(0, $batch->made(Attendance::class)->count());
        $this->assertSame(0, $batch->made(Guardianship::class)->count());
        $this->assertGreaterThan(0, $batch->made(ClassSession::class)->count());
    }

    public function test_deleting_is_refused_while_something_made_since_uses_its_rows(): void
    {
        $this->generate(['members' => [$this->roles['learner']->id => 2]]);
        $batch = SampleBatch::query()->sole();
        $term = $batch->made(Term::class)->sole();
        $real = Classroom::factory()->create(['tenant_id' => $this->branch->id, 'term_id' => $term->id]);

        Livewire::test(ListSampleBatches::class)
            ->callAction(TestAction::make('erase')->table($batch))
            ->assertNotified('داده‌ها پاک نشد');

        $this->assertModelExists($batch);
        $this->assertModelExists($term);
        $this->assertModelExists($real);
        $this->assertSame(1, $batch->made(Classroom::class)->count());
    }

    public function test_rows_of_a_batch_are_not_part_of_a_running_tutorial(): void
    {
        $run = $this->startTutorial($this->owner);

        $this->generate(['members' => [$this->roles['learner']->id => 2]]);

        $this->assertSame(0, TutorialRun::query()->find($run->id)->records()->count());
        $this->assertGreaterThan(0, SampleBatch::query()->sole()->records()->count());
    }

    public function test_only_super_admins_see_it(): void
    {
        $this->get(SampleBatchResource::getUrl('index', panel: 'admin', tenant: $this->primary))
            ->assertOk()
            ->assertSee('ساخت داده‌ی نمونه')
            ->assertSee('آموزش سامانه');

        $manager = User::factory()->create();
        $this->grantRole($manager, $this->primary, DefaultRoles::TENANT_MANAGER);

        $this->actingAs($manager)
            ->get(SampleBatchResource::getUrl('index', panel: 'admin', tenant: $this->primary))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function generate(array $data, bool $expectSuccess = true): void
    {
        $test = Livewire::test(ListSampleBatches::class)
            ->callAction('generate', data: ['tenant_id' => $this->branch->id, 'password' => 'secret-pass', 'homework_percent' => 100, 'members' => [], ...$data]);

        $expectSuccess
            ? $test->assertHasNoActionErrors()->assertNotified()
            : $test->assertNotified('داده‌ی نمونه ساخته نشد');
    }
}
