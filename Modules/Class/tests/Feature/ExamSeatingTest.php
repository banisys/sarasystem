<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->mountAction($this->action())
            ->assertActionMounted($this->action());
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
            ->mountAction($this->action())
            ->assertSchemaStateSet(['layout' => $layout], 'mountedActionSchema0');
    }

    public function test_invalid_layouts_cannot_assign_a_non_attendee_or_duplicate_a_person(): void
    {
        $student = $this->attendee('شرکت‌کننده');
        $outsider = User::factory()->create();

        foreach ([[$student->id, $outsider->id], [$student->id, $student->id], [$student->id]] as $seats) {
            Livewire::test(ViewExamSession::class, ['record' => $this->exam->id])
                ->callAction($this->action(), data: ['layout' => ['rows' => 1, 'columns' => 2, 'seats' => $seats]])
                ->assertActionMounted($this->action());
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
            ->mountAction($this->action())
            ->assertActionNotMounted($this->action());

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

    private function action(): TestAction
    {
        return TestAction::make('seatLayout')->schemaComponent('sessionAttendance', 'infolist');
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
