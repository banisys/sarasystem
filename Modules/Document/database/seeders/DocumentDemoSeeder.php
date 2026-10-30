<?php

namespace Modules\Document\Database\Seeders;

use App\Documents\DocumentHolders;
use App\Models\FormCustomization;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Workflow;
use Database\Seeders\Concerns\GrantsDemoPermissions;
use Illuminate\Database\Seeder;
use Modules\Document\Enums\FileKind;
use Modules\Document\Filament\Resources\DocumentTypes\Schemas\DocumentTypeForm;
use Modules\Document\Models\DocumentType;
use Modules\Document\Services\DocumentFormFields;
use Modules\Document\Workflow\DocumentStepExtension;
use Modules\Employee\Models\JobPosition;
use RuntimeException;

/**
 * Sample document types for local development (ADR 0019), on top of the
 * core DemoSeeder and the Employee / Student demo workflows: national card
 * and birth certificate for everyone, a degree for teachers, a health
 * certificate with an expiry date. The first step of each demo workflow
 * checks the applicant's documents. The demo roles get the documents
 * permissions. Safe to run again.
 */
class DocumentDemoSeeder extends Seeder
{
    use GrantsDemoPermissions;

    public function run(): void
    {
        $headOffice = Tenant::primary() ?? throw new RuntimeException('No primary tenant: run the core seeder first.');
        $hq = $this->roleIds(['hq_member']);
        $checkers = $this->roleIds(['hq_member', 'principal']);

        // The documents screens; uploading and checking also follow each
        // type's roles (ADR 0019).
        $this->grantDemoPermissions([
            'hq_member' => ['ViewAny:Document', 'View:Document', 'Create:Document', 'Update:Document', 'Delete:Document',
                'ViewAny:DocumentType', 'View:DocumentType', 'Create:DocumentType', 'Update:DocumentType', 'Delete:DocumentType', 'Reorder:DocumentType'],
            'principal' => ['ViewAny:Document', 'View:Document', 'Create:Document', 'Update:Document'],
        ]);

        $headOffice->execute(function () use ($hq, $checkers): void {
            $idCard = $this->type('کارت ملی', uploaders: $hq, verifiers: $checkers, maxFiles: 2, description: 'تصویر پشت و روی کارت.');
            $birthCertificate = $this->type('شناسنامه', uploaders: $hq, verifiers: $checkers, maxFiles: 3, description: 'صفحه‌ی اول و صفحه‌ی توضیحات.');
            $degree = $this->type('آخرین مدرک تحصیلی', uploaders: $hq, verifiers: $checkers, kind: FileKind::ImageOrPdf);
            $this->type('گواهی سلامت', uploaders: $hq, verifiers: $checkers, expiry: true);
            $photo = $this->type('عکس پرسنلی', uploaders: $hq, verifiers: $checkers, kind: FileKind::Image);

            foreach (['employee', 'learner'] as $holder) {
                $this->formModes($holder, null, [
                    DocumentFormFields::key($idCard->id) => 'required',
                    DocumentFormFields::key($birthCertificate->id) => 'required',
                    DocumentFormFields::key($photo->id) => 'optional',
                    DocumentFormFields::key($degree->id) => 'hidden',
                ]);
            }

            foreach (JobPosition::query()->where('title', 'like', 'معلم%')->pluck('id') as $positionId) {
                $this->formModes('employee', $positionId, [DocumentFormFields::key($degree->id) => 'required']);
            }

            $this->checkInFirstStep('استخدام کادر آموزشی (نمونه)', [$idCard->id, $birthCertificate->id, $degree->id]);
            $this->checkInFirstStep('پذیرش دانش‌آموز (نمونه)', [$idCard->id, $birthCertificate->id]);
        });
    }

    /**
     * @param  list<int>  $uploaders
     * @param  list<int>  $verifiers
     */
    private function type(string $title, array $uploaders, array $verifiers, int $maxFiles = 1, FileKind $kind = FileKind::ImageOrPdf, bool $expiry = false, ?string $description = null): DocumentType
    {
        return DocumentTypeForm::save(DocumentType::query()->where('title', $title)->first(), [
            'title' => $title,
            'description' => $description,
            'file_kind' => $kind,
            'max_files' => $maxFiles,
            'max_kilobytes' => 5120,
            'has_expiry' => $expiry,
            'upload_role_ids' => $uploaders,
            'verify_role_ids' => $verifiers,
            'is_active' => true,
        ]);
    }

    /** @param array<string, string> $modes */
    private function formModes(string $holderKey, ?int $ownerId, array $modes): void
    {
        $holder = DocumentHolders::find($holderKey);
        $row = FormCustomization::query()->firstOrNew([
            'form' => $holder->form()->key(),
            'owner_type' => $ownerId === null ? null : (new ($holder->requirementOwnerModel()))->getMorphClass(),
            'owner_id' => $ownerId,
        ]);
        $row->field_modes = [...($row->field_modes ?? []), ...$modes];
        $row->fields ??= [];
        $row->save();
    }

    /**
     * @param  list<int>  $typeIds
     */
    private function checkInFirstStep(string $workflowName, array $typeIds): void
    {
        $workflow = Workflow::query()->where('name', $workflowName)->first();

        if ($workflow === null) {
            return;
        }

        $steps = $workflow->steps;
        $steps[0]['extensions'][DocumentStepExtension::KEY] = ['upload' => $typeIds, 'verify' => $typeIds];
        $workflow->update(['steps' => $steps]);
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function roleIds(array $names): array
    {
        return Role::query()->whereNull('tenant_id')->whereIn('name', $names)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
