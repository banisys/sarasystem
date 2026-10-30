<?php

namespace Modules\Document\Tests\Feature;

use App\Documents\ApplicantDocuments;
use App\Models\FormCustomization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Document\Models\DocumentType;
use Modules\Document\Models\DocumentTypeRequirement;
use Modules\Document\Services\DocumentFormFields;
use Modules\Employee\Forms\JobApplicationForm;
use Modules\Employee\Models\JobPosition;
use Modules\Student\Filament\Resources\Programs\Pages\ListPrograms;
use Modules\Student\Models\Program;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DocumentFormSettingsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_program_form_settings_save_document_modes_and_per_program_overrides(): void
    {
        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $type = DocumentType::factory()->for($primary)->create(['title' => 'کارت ملی']);
        $program = Program::factory()->for($primary)->create(['title' => 'زبان انگلیسی']);
        $this->actingInPanel(User::factory()->superAdmin()->create(), $primary);
        $key = DocumentFormFields::key($type->id);

        Livewire::test(ListPrograms::class)
            ->mountAction('customizeForm')
            ->assertSchemaStateSet(['field_modes.'.$key => 'hidden'])
            ->setActionData(['field_modes.'.$key => 'required'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertTrue(app(ApplicantDocuments::class)->fields('learner', $program->id)[0]['required']);

        Livewire::test(ListPrograms::class)
            ->callTableAction('customizeForm', $program, data: ['field_modes' => [$key => 'optional']])
            ->assertHasNoTableActionErrors();

        $this->assertFalse(app(ApplicantDocuments::class)->fields('learner', $program->id)[0]['required']);
        $this->assertTrue(app(ApplicantDocuments::class)->fields('learner', null)[0]['required']);
        $this->assertSame([], app(ApplicantDocuments::class)->fields('employee', null));

        Livewire::test(ListPrograms::class)
            ->callTableAction('customizeForm', $program, data: ['field_modes' => [$key => null]])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(app(ApplicantDocuments::class)->fields('learner', $program->id)[0]['required']);
    }

    public function test_migration_preserves_existing_forms_and_moves_legacy_requirements(): void
    {
        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $position = JobPosition::factory()->for($primary)->create();
        $program = Program::factory()->for($primary)->create();
        $type = DocumentType::factory()->for($primary)->create();
        DB::table('document_types')->where('id', $type->id)->update([
            'holders' => json_encode(['employee', 'learner']), 'applicant_uploads' => true, 'required_for' => 'selected',
        ]);
        DocumentTypeRequirement::query()->create(['document_type_id' => $type->id, 'holder' => 'employee', 'owner_id' => $position->id]);
        DocumentTypeRequirement::query()->create(['document_type_id' => $type->id, 'holder' => 'learner', 'owner_id' => $program->id]);
        $fields = [['key' => 'existing', 'type' => 'text', 'label' => 'یادداشت', 'section' => 'personal', 'required' => false]];
        $row = FormCustomization::query()->create([
            'tenant_id' => $primary->id, 'form' => JobApplicationForm::KEY,
            'owner_type' => $position->getMorphClass(), 'owner_id' => $position->id,
            'field_modes' => ['email' => 'optional'], 'fields' => $fields,
        ]);
        $optional = DocumentType::factory()->for($primary)->create();
        DB::table('document_types')->where('id', $optional->id)->update([
            'holders' => json_encode(['learner']), 'applicant_uploads' => true, 'required_for' => 'nobody',
        ]);
        $foreign = DocumentType::factory()->create();
        DB::table('document_types')->where('id', $foreign->id)->update([
            'holders' => json_encode(['learner']), 'applicant_uploads' => true, 'required_for' => 'everyone',
        ]);

        $migration = require base_path('Modules/Document/database/migrations/2026_10_30_194103_move_document_requirements_to_form_customizations.php');
        $migration->up();

        $this->assertSame('optional', $row->fresh()->field_modes['email']);
        $this->assertSame($fields, $row->fresh()->fields);
        $documents = app(ApplicantDocuments::class);
        $this->assertSame([$type->id], array_column($documents->fields('employee', $position->id), 'id'));
        $this->assertSame([], $documents->fields('employee', null));
        $this->assertSame([$optional->id], array_column($documents->fields('learner', null), 'id'));
        $this->assertFalse($documents->fields('learner', null)[0]['required']);
        $programFields = collect($documents->fields('learner', $program->id))->keyBy('id');
        $this->assertTrue($programFields[$type->id]['required']);
        $this->assertArrayNotHasKey($foreign->id, $programFields->all());

        // A later form choice wins if the migration is run again.
        $modes = $row->fresh()->field_modes;
        $row->update(['field_modes' => [...$modes, DocumentFormFields::key($type->id) => 'hidden']]);
        $migration->up();

        $this->assertSame([], $documents->fields('employee', $position->id));
    }
}
