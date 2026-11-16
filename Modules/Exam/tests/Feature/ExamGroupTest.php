<?php

namespace Modules\Exam\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Exam\Filament\Resources\ExamGroups\ExamGroupResource;
use Modules\Exam\Filament\Resources\ExamGroups\Pages\ManageExamGroups;
use Modules\Exam\Filament\Resources\Exams\Pages\EditExam;
use Modules\Exam\Filament\Resources\Exams\Pages\ListExams;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamGroup;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ExamGroupTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_officers_manage_only_their_branch_groups_and_deleting_a_group_keeps_its_exams(): void
    {
        $tenant = Tenant::factory()->create();
        $officer = User::factory()->create();
        $this->makeStaff($officer, $tenant);
        app(DefaultRoles::class)->install();
        $this->grantRole($officer, $tenant, DefaultRoles::EDUCATION_OFFICER);
        $this->actingInPanel($officer, $tenant);
        $foreign = ExamGroup::factory()->create(['title' => 'خرداد ۱۴۰۵']);

        $this->get(ExamGroupResource::getUrl('index', tenant: $tenant))
            ->assertOk()
            ->assertSee('افزودن گروه آزمون');

        Livewire::test(ManageExamGroups::class)
            ->assertCanNotSeeTableRecords([$foreign])
            ->callAction('create', data: ['title' => ' خرداد ۱۴۰۵ '])
            ->assertHasNoActionErrors()
            ->callAction('create', data: ['title' => 'خرداد ۱۴۰۵'])
            ->assertHasActionErrors(['title']);

        $group = ExamGroup::query()->sole();
        $this->assertSame('خرداد ۱۴۰۵', $group->title);
        $exam = Exam::factory()->for($tenant)->for($group)->create();

        Livewire::test(ManageExamGroups::class)
            ->assertCanSeeTableRecords([$group])
            ->assertTableColumnStateSet('exams_count', 1, record: $group)
            ->callAction(TestAction::make('edit')->table($group), data: ['title' => 'نیم‌سال ۱۴۰۵'])
            ->assertHasNoActionErrors();

        $this->assertSame('نیم‌سال ۱۴۰۵', $group->refresh()->title);

        Livewire::test(ManageExamGroups::class)
            ->callAction(TestAction::make('delete')->table($group))
            ->assertHasNoActionErrors();

        $this->assertModelMissing($group);
        $this->assertNull($exam->refresh()->exam_group_id);
        $this->assertModelExists($foreign);
    }

    public function test_the_exam_list_filters_by_group_and_shows_ungrouped_exams_before_filtering(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);
        $group = ExamGroup::factory()->for($tenant)->create(['title' => 'خرداد ۱۴۰۵']);
        $exam = Exam::factory()->for($tenant)->for($group)->create();
        $ungrouped = Exam::factory()->for($tenant)->create();

        Livewire::test(ListExams::class)
            ->assertCanSeeTableRecords([$exam, $ungrouped])
            ->assertTableColumnStateSet('examGroup.title', $group->title, record: $exam)
            ->filterTable('examGroup', $group->id)
            ->assertCanSeeTableRecords([$exam])
            ->assertCanNotSeeTableRecords([$ungrouped]);
    }

    public function test_exam_edit_can_assign_and_clear_a_group_but_rejects_a_foreign_group(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);
        $group = ExamGroup::factory()->for($tenant)->create();
        $foreign = ExamGroup::factory()->create();
        $exam = Exam::factory()->for($tenant)->create();

        Livewire::test(EditExam::class, ['record' => $exam->id])
            ->fillForm(['exam_group_id' => $foreign->id])
            ->call('save')
            ->assertHasFormErrors(['exam_group_id']);

        $this->assertNull($exam->refresh()->exam_group_id);

        Livewire::test(EditExam::class, ['record' => $exam->id])
            ->fillForm(['exam_group_id' => $group->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($group->id, $exam->refresh()->exam_group_id);

        Livewire::test(EditExam::class, ['record' => $exam->id])
            ->fillForm(['exam_group_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($exam->refresh()->exam_group_id);
    }

    public function test_database_rejects_groups_from_another_branch(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = ExamGroup::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('exams_tenant_id_exam_group_id_foreign');

        Exam::factory()->for($tenant)->for($foreign)->create();
    }

    public function test_panel_access_alone_does_not_allow_managing_exam_groups(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create();
        $this->makeStaff($staff, $tenant);

        $this->actingAs($staff)
            ->get(ExamGroupResource::getUrl('index', tenant: $tenant))
            ->assertForbidden();
    }
}
