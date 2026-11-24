<?php

namespace Modules\Class\Tests\Feature;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ViewExamSession;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionScene;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ExamSeatingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $branch;

    private ClassSession $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $this->branch = Tenant::factory()->create();
        $this->branch->makeCurrent();
        $classroom = Classroom::factory()->for($this->branch)->create();
        $this->exam = ClassSession::factory()->create(['classroom_id' => $classroom->id, 'kind' => SessionKind::Exam]);
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($primary);
        $this->actingInPanel($admin, $this->branch);
    }

    public function test_the_editor_saves_and_reopens_the_same_seats_and_the_scene_follows_the_plan(): void
    {
        $first = $this->attendee('الف');
        $second = $this->attendee('ب');
        $attendance = Attendance::factory()->create([
            'session_id' => $this->exam->id, 'classroom_id' => $this->exam->classroom_id,
            'user_id' => $first->id, 'status' => AttendanceStatus::Absent,
        ]);
        $otherExam = ClassSession::factory()->create(['classroom_id' => $this->exam->classroom_id, 'kind' => SessionKind::Exam]);
        $layout = ['rows' => 2, 'columns' => 2, 'seats' => [$second->id, null, $first->id, null]];

        $editor = Livewire::test(ViewExamSession::class, ['record' => $this->exam->id])
            ->mountAction('seatLayout')
            ->assertActionMounted('seatLayout');
        $this->assertStringContainsString('چیدمان خودکار', $editor->effects['partials']['action-modals'] ?? $editor->html());
        $editor->fillForm(['layout' => $layout], 'mountedActionSchema0')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('چیدمان صندلی‌ها ذخیره شد.');
        if (Module::isEnabled('Campus')) {
            $editor->assertSee('چیدمان ذخیره‌شده');
        }

        $exam = $this->exam->fresh();
        $this->assertSame($layout, $exam->seat_layout);
        $this->assertSame(AttendanceStatus::Absent, $attendance->fresh()->status);
        $this->assertNull($otherExam->fresh()->seat_layout);
        $scene = app(SessionScene::class)->for($exam);
        $this->assertSame(['rows' => 2, 'columns' => 2, 'seats' => [1, null, 0, null]], $scene['layout']);
        $this->assertSame('absent', $scene['seats'][0]['status']);

        Livewire::test(ViewExamSession::class, ['record' => $exam->id])
            ->mountAction('seatLayout')
            ->assertSchemaStateSet(['layout' => $layout], 'mountedActionSchema0');
    }

    public function test_the_editor_can_save_a_layout_with_every_seat_empty(): void
    {
        $student = $this->attendee('شرکت‌کننده');
        $this->exam->update([
            'seat_layout' => ['rows' => 1, 'columns' => 2, 'seats' => [$student->id, null]],
        ]);
        $emptyLayout = ['rows' => 1, 'columns' => 2, 'seats' => [null, null]];

        Livewire::test(ViewExamSession::class, ['record' => $this->exam->id])
            ->callAction('seatLayout', data: ['layout' => $emptyLayout])
            ->assertHasNoActionErrors()
            ->assertNotified('چیدمان صندلی‌ها ذخیره شد.');

        $this->assertSame($emptyLayout, $this->exam->fresh()->seat_layout);
    }

    public function test_invalid_layouts_cannot_assign_a_non_attendee_or_duplicate_a_person(): void
    {
        $student = $this->attendee('شرکت‌کننده');
        $outsider = User::factory()->create();

        foreach ([[$student->id, $outsider->id], [$student->id, $student->id], [$student->id]] as $seats) {
            Livewire::test(ViewExamSession::class, ['record' => $this->exam->id])
                ->callAction('seatLayout', data: ['layout' => ['rows' => 1, 'columns' => 2, 'seats' => $seats]])
                ->assertActionMounted('seatLayout');
            $this->assertNull($this->exam->fresh()->seat_layout);
        }
    }

    public function test_viewers_cannot_edit_the_plan_and_the_exam_is_invisible_in_another_branch(): void
    {
        $viewer = User::factory()->create();
        $this->roleWithPermissions('class-viewer', ['View:Classroom']);
        $this->grantRole($viewer, $this->branch, 'class-viewer');
        $this->actingInPanel($viewer, $this->branch);

        Livewire::test(ViewExamSession::class, ['record' => $this->exam->id])
            ->assertDontSee('چیدمان صندلی‌ها')
            ->mountAction('seatLayout')
            ->assertActionNotMounted('seatLayout');

        $other = Tenant::factory()->create();
        $admin = User::factory()->create();
        $admin->makeSuperAdmin(Tenant::primary());
        $this->actingInPanel($admin, $other);
        $this->get(ClassSessionResource::getUrl('view-exam', ['record' => $this->exam], tenant: $other))->assertNotFound();
    }

    public function test_cancelled_exams_cannot_edit_the_plan(): void
    {
        $this->exam->update(['status' => SessionStatus::Cancelled]);

        Livewire::test(ViewExamSession::class, ['record' => $this->exam->id])->assertDontSee('چیدمان صندلی‌ها');
    }

    public function test_a_class_editor_can_save_the_shared_seating_plan_from_the_user_panel(): void
    {
        $student = $this->attendee('شرکت‌کننده');
        $outsider = User::factory()->create();
        $this->exam->teachers()->attach(auth()->id());
        $this->withSession([ResolveCurrentTenant::SESSION_KEY => $this->branch->id]);
        $sessionUrl = route('my-classes.session', $this->exam);
        $seatingUrl = route('my-classes.seating.update', $this->exam);
        $layout = ['rows' => 1, 'columns' => 2, 'seats' => [null, $student->id]];

        $this->get($sessionUrl)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seating.attendees', [['id' => $student->id, 'name' => $student->name]])
            ->where('seating.layout.seats', [null, null, null, null]));

        $this->from($sessionUrl)->patch($seatingUrl, [
            'layout' => ['rows' => 1, 'columns' => 2, 'seats' => [null, $outsider->id]],
        ])->assertSessionHasErrors('form');
        $this->assertNull($this->exam->fresh()->seat_layout);

        $this->patch($seatingUrl, ['layout' => $layout])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'چیدمان صندلی‌ها ذخیره شد.')
            ->assertRedirect($sessionUrl);
        $this->assertSame($layout, $this->exam->fresh()->seat_layout);

        $this->get($sessionUrl)->assertInertia(fn (AssertableInertia $page) => $page->where('seating.layout', $layout));
        Livewire::test(ViewExamSession::class, ['record' => $this->exam->id])
            ->mountAction('seatLayout')
            ->assertSchemaStateSet(['layout' => $layout], 'mountedActionSchema0');
    }

    public function test_the_user_panel_checks_exam_edit_permissions_and_branch_scope(): void
    {
        $editor = auth()->user();
        $this->exam->teachers()->attach($editor->id);
        $student = $this->attendee('شرکت‌کننده');
        $this->grantRole($student, $this->branch, 'student');
        $sessionUrl = route('my-classes.session', $this->exam);
        $seatingUrl = route('my-classes.seating.update', $this->exam);
        $data = ['layout' => ['rows' => 1, 'columns' => 1, 'seats' => [$student->id]]];

        $this->actingAs($student)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->branch->id]);
        $this->get($sessionUrl)->assertInertia(fn (AssertableInertia $page) => $page->where('seating', null));
        $this->patch($seatingUrl, $data)->assertForbidden();

        $this->actingAs($editor)->withSession([ResolveCurrentTenant::SESSION_KEY => Tenant::primary()->id]);
        $this->patch($seatingUrl, $data)->assertNotFound();

        $this->withSession([ResolveCurrentTenant::SESSION_KEY => $this->branch->id]);
        $this->exam->update(['status' => SessionStatus::Cancelled]);
        $this->get($sessionUrl)->assertInertia(fn (AssertableInertia $page) => $page->where('seating', null));
        $this->patch($seatingUrl, $data)->assertForbidden();
        $this->assertNull($this->exam->fresh()->seat_layout);
    }

    public function test_only_the_effective_invigilator_inherits_seating_access_from_the_exam_assignment(): void
    {
        [$invigilator, $substitute] = User::factory()->count(2)->create();
        $this->grantRole($invigilator, $this->branch, 'teacher');
        $this->grantRole($substitute, $this->branch, 'teacher');
        $this->exam->teachers()->attach($invigilator->id, ['substitute_id' => $substitute->id]);
        $student = $this->attendee('شرکت‌کننده');
        $sessionUrl = route('my-classes.session', $this->exam);
        $seatingUrl = route('my-classes.seating.update', $this->exam);
        $layout = ['rows' => 1, 'columns' => 1, 'seats' => [$student->id]];

        $this->actingAs($invigilator)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->branch->id]);
        $this->get($sessionUrl)->assertInertia(fn (AssertableInertia $page) => $page->where('seating', null));
        $this->patch($seatingUrl, ['layout' => $layout])->assertForbidden();

        $this->actingAs($substitute);
        $this->get($sessionUrl)->assertInertia(fn (AssertableInertia $page) => $page->where('seating.attendees.0.id', $student->id));
        $this->patch($seatingUrl, ['layout' => $layout])->assertSessionHasNoErrors()->assertRedirect($sessionUrl);
        $this->assertSame($layout, $this->exam->fresh()->seat_layout);

        $this->exam->teachers()->updateExistingPivot($invigilator->id, ['substitute_id' => null]);
        $this->actingAs($invigilator);
        $this->get($sessionUrl)->assertInertia(fn (AssertableInertia $page) => $page->where('seating.layout', $layout));
        $this->patch($seatingUrl, ['layout' => ['rows' => 1, 'columns' => 1, 'seats' => [null]]])
            ->assertSessionHasNoErrors()->assertRedirect($sessionUrl);
        $this->assertSame([null], $this->exam->fresh()->seat_layout['seats']);
    }

    public function test_an_admin_can_lock_seating_changes_from_the_user_panel_without_changing_the_plan(): void
    {
        $admin = auth()->user();
        $invigilator = User::factory()->create();
        $this->grantRole($invigilator, $this->branch, 'teacher');
        $this->exam->teachers()->attach($invigilator->id);
        $student = $this->attendee('شرکت‌کننده');
        $layout = ['rows' => 1, 'columns' => 1, 'seats' => [$student->id]];
        $emptyLayout = ['rows' => 1, 'columns' => 1, 'seats' => [null]];
        $this->exam->forceFill(['seat_layout' => $layout])->save();
        $page = ['record' => $this->exam->id];

        Livewire::test(ViewExamSession::class, $page)
            ->assertActionHasIcon('seatLayout', Heroicon::OutlinedTableCells)
            ->callAction(['seatLayout', 'lockSeatLayout'])
            ->assertNotified('چیدمان در پنل کاربری قفل شد.');
        $this->assertTrue($this->exam->refresh()->seat_layout_user_panel_locked);
        $this->assertSame($layout, $this->exam->seat_layout);

        $sessionUrl = route('my-classes.session', $this->exam);
        $seatingUrl = route('my-classes.seating.update', $this->exam);
        $this->actingAs($invigilator)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->branch->id]);
        $this->get($sessionUrl)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seating.locked', true)
            ->where('seating.layout', $layout));
        $this->patch($seatingUrl, ['layout' => $emptyLayout, 'locked' => false])->assertForbidden();
        $this->assertSame($layout, $this->exam->refresh()->seat_layout);

        $this->actingInPanel($admin, $this->branch);
        Livewire::test(ViewExamSession::class, $page)
            ->assertActionHasIcon('seatLayout', Heroicon::OutlinedLockClosed)
            ->callAction('seatLayout', data: ['layout' => $emptyLayout])
            ->assertHasNoActionErrors();
        $this->assertTrue($this->exam->refresh()->seat_layout_user_panel_locked);
        $this->assertSame($emptyLayout, $this->exam->seat_layout);

        Livewire::test(ViewExamSession::class, $page)
            ->callAction(['seatLayout', 'unlockSeatLayout'])
            ->assertNotified('قفل چیدمان در پنل کاربری باز شد.');
        $this->assertFalse($this->exam->refresh()->seat_layout_user_panel_locked);

        $this->actingAs($invigilator);
        $this->patch($seatingUrl, ['layout' => $layout])->assertSessionHasNoErrors()->assertRedirect($sessionUrl);
        $this->assertSame($layout, $this->exam->refresh()->seat_layout);
    }

    private function attendee(string $name): User
    {
        $student = User::factory()->create(['name' => $name]);
        ClassMember::factory()->create([
            'classroom_id' => $this->exam->classroom_id,
            'user_id' => $student->id,
            'class_role_id' => ClassRole::factory()->create(['is_attendee' => true])->id,
            'joined_on' => null,
            'left_on' => null,
        ]);

        return $student;
    }
}
