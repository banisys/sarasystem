<?php

namespace Modules\Employee\Tests\Feature;

use App\Models\FormCustomization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Employee\Filament\Resources\JobApplications\JobApplicationResource;
use Modules\Employee\Filament\Resources\JobApplications\Pages\ListJobApplications;
use Modules\Employee\Filament\Resources\JobApplications\Pages\ViewJobApplication;
use Modules\Employee\Filament\Resources\JobPositions\JobPositionResource;
use Modules\Employee\Filament\Resources\JobPositions\Pages\ListJobPositions;
use Modules\Employee\Forms\JobApplicationForm;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class JobApplicationAdminTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create();

        $permissions = collect(['JobApplication', 'JobPosition'])
            ->flatMap(fn (string $subject): array => ["ViewAny:{$subject}", "View:{$subject}", "Create:{$subject}", "Update:{$subject}", "Delete:{$subject}"])
            ->all();
        $this->roleWithPermissions('hr_manager', $permissions);

        $this->manager = User::factory()->create();
        foreach ([$this->primary, $this->west] as $tenant) {
            $this->makeStaff($this->manager, $tenant);
            $this->grantRole($this->manager, $tenant, 'hr_manager');
        }
    }

    public function test_lists_show_only_the_current_tenants_records(): void
    {
        $ownApplication = JobApplication::factory()->for($this->primary)->create();
        $foreignApplication = JobApplication::factory()->for($this->west)->create();
        $ownPosition = JobPosition::factory()->for($this->primary)->create();
        $foreignPosition = JobPosition::factory()->for($this->west)->create();

        $this->actingInPanel($this->manager, $this->primary);

        Livewire::test(ListJobApplications::class)->assertCanSeeTableRecords([$ownApplication])->assertCanNotSeeTableRecords([$foreignApplication]);
        Livewire::test(ListJobPositions::class)->assertCanSeeTableRecords([$ownPosition])->assertCanNotSeeTableRecords([$foreignPosition]);

        $this->get(JobApplicationResource::getUrl('view', ['record' => $foreignApplication], tenant: $this->primary))->assertNotFound();
    }

    public function test_the_resources_are_only_in_the_primary_tenants_panel(): void
    {
        $this->actingAs($this->manager);

        $this->get(JobApplicationResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(JobPositionResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(JobApplicationResource::getUrl('index', tenant: $this->west))->assertForbidden();
        $this->get(JobPositionResource::getUrl('index', tenant: $this->west))->assertForbidden();
    }

    public function test_staff_without_permission_cannot_see_applications(): void
    {
        $clerk = User::factory()->create();
        $this->makeStaff($clerk, $this->primary);

        $this->actingAs($clerk)->get(JobApplicationResource::getUrl('index', tenant: $this->primary))->assertForbidden();
    }

    public function test_an_application_can_be_viewed_and_its_resume_downloaded(): void
    {
        $application = JobApplication::factory()->for($this->primary)->create([
            'national_code' => '0013542419',
            'work_history' => [['company_name' => 'مدرسه نمونه', 'job_position' => 'معلم', 'start_date' => '2016-09-22', 'end_date' => null, 'termination_reason' => null, 'company_phone' => null]],
        ]);
        $application->addMedia(CareersFormTest::pdf())->toMediaCollection(JobApplication::RESUME_COLLECTION);

        $this->actingAs($this->manager)
            ->get(JobApplicationResource::getUrl('view', ['record' => $application], tenant: $this->primary))
            ->assertOk()
            ->assertSee($application->fullName())
            ->assertSee('مدرسه نمونه')
            ->assertSee('دانشگاه تهران');

        $this->actingInPanel($this->manager, $this->primary);

        Livewire::test(ViewJobApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('downloadResume')
            ->assertFileDownloaded('resume-0013542419.pdf');
    }

    public function test_job_positions_are_created_in_the_current_tenant(): void
    {
        $this->actingInPanel($this->manager, $this->primary);

        Livewire::test(ListJobPositions::class)
            ->callAction('create', data: ['title' => 'معاون آموزشی', 'is_active' => true])
            ->assertHasNoActionErrors();

        $this->assertSame($this->primary->id, JobPosition::query()->sole()->tenant_id);
    }

    public function test_the_form_is_set_up_for_all_positions_and_per_position(): void
    {
        $position = JobPosition::factory()->for($this->primary)->create();
        $this->actingInPanel($this->manager, $this->primary);

        Livewire::test(ListJobPositions::class)
            ->mountAction('customizeForm')
            ->assertSchemaStateSet(['field_modes.father_name' => 'required', 'field_modes.phone' => 'optional'])
            ->setActionData([
                'field_modes.father_name' => 'hidden',
                'fields' => [['key' => 'kanoon', 'label' => 'عضو کانون هستید؟', 'type' => 'select', 'options' => ['بله', 'خیر'], 'section' => 'personal', 'required' => true, 'help' => null]],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        Livewire::test(ListJobPositions::class)
            ->callTableAction('customizeForm', $position, data: [
                'field_modes' => ['email' => 'optional'],
                'fields' => [['key' => '', 'label' => 'شیفت شب', 'type' => 'checkbox', 'section' => 'resume', 'required' => false, 'help' => null]],
            ])
            ->assertHasNoTableActionErrors();

        $general = FormCustomization::query()->whereNull('owner_type')->sole();
        $this->assertSame(JobApplicationForm::KEY, $general->form);
        $this->assertSame('hidden', $general->field_modes['father_name']);
        $this->assertSame(['بله', 'خیر'], $general->fields[0]['options']);

        $own = FormCustomization::query()->whereMorphedTo('owner', $position)->sole();
        $this->assertSame($this->primary->id, $own->tenant_id);
        // Empty modes mean "as in the whole form" and are not stored.
        $this->assertSame(['email' => 'optional'], $own->field_modes);
        $this->assertNotEmpty($own->fields[0]['key']);

        $form = JobApplicationForm::for($this->primary, $position);
        $this->assertFalse($form->asks('father_name'));
        $this->assertSame('optional', $form->mode('email')->value);
        $this->assertSame(['kanoon', $own->fields[0]['key']], array_column(array_map(fn ($field) => $field->toArray(), $form->fields), 'key'));

        $position->delete();
        $this->assertFalse(FormCustomization::query()->whereNotNull('owner_type')->exists());
    }

    public function test_staff_who_cannot_edit_positions_cannot_set_up_the_form(): void
    {
        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $this->primary);
        $this->roleWithPermissions('hr_viewer', ['ViewAny:JobPosition', 'View:JobPosition']);
        $this->grantRole($viewer, $this->primary, 'hr_viewer');
        $position = JobPosition::factory()->for($this->primary)->create();

        $this->actingInPanel($viewer, $this->primary);

        Livewire::test(ListJobPositions::class)
            ->assertActionHidden('customizeForm')
            ->assertTableActionHidden('customizeForm', $position);
    }

    public function test_the_answers_to_added_fields_are_shown_with_the_application(): void
    {
        $application = JobApplication::factory()->for($this->primary)->create([
            'custom_fields' => [
                ['key' => 'kanoon', 'label' => 'عضو کانون هستید؟', 'type' => 'select', 'section' => 'personal', 'value' => 'بله'],
                ['key' => 'start', 'label' => 'زمان آمادگی', 'type' => 'date', 'section' => 'resume', 'value' => '2026-10-23'],
                ['key' => 'shift', 'label' => 'شیفت شب', 'type' => 'checkbox', 'section' => 'resume', 'value' => false],
            ],
        ]);

        $this->actingAs($this->manager)
            ->get(JobApplicationResource::getUrl('view', ['record' => $application], tenant: $this->primary))
            ->assertOk()
            ->assertSee('اطلاعات تکمیلی')
            ->assertSee('عضو کانون هستید؟')
            ->assertSee('1405/08/01')
            ->assertSee('خیر');
    }
}
