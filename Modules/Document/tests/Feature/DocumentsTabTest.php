<?php

namespace Modules\Document\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Filament\RelationManagers\DocumentsRelationManager;
use Modules\Document\Models\Document;
use Modules\Document\Tests\Concerns\MakesDocuments;
use Modules\Employee\Filament\Resources\Employees\Pages\ViewEmployee;
use Modules\Employee\Models\Employee;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The «مدارک» tab on employees' and learners' pages (ADR 0019).
 */
class DocumentsTabTest extends TestCase
{
    use InteractsWithTenants, MakesDocuments, RefreshDatabase;

    public function test_the_tab_lists_the_persons_documents_and_uploads_one(): void
    {
        Storage::fake('local');

        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $branch = Tenant::factory()->create();
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($primary);

        $idCard = $this->documentType($primary, ['title' => 'کارت ملی']);
        $employee = Employee::factory()->for($branch)->create();
        $mine = $this->uploadFor($employee, $this->documentType($primary, ['title' => 'شناسنامه']));
        $someoneElses = $this->uploadFor(Employee::factory()->for($branch)->create(), $idCard);

        $this->actingInPanel($admin, $branch);
        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => ViewEmployee::class])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$someoneElses])
            ->assertTableActionVisible('approve', $mine)
            ->callAction(TestAction::make('upload')->table(), data: ['document_type_id' => $idCard->id, 'files' => [self::image()]])
            ->assertHasNoActionErrors();

        $uploaded = Document::withoutTenantScope()->whereBelongsTo($idCard)->whereMorphedTo('documentable', $employee)->sole();
        $this->assertSame(DocumentStatus::Pending, $uploaded->status);
        $this->assertSame($branch->id, $uploaded->tenant_id);
        $this->assertSame($admin->id, $uploaded->uploaded_by_id);
    }
}
