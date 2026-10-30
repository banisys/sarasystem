<?php

namespace Modules\Document\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Document\Enums\FileKind;
use Modules\Document\Models\DocumentType;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->unique()->words(2, true),
            'file_kind' => FileKind::ImageOrPdf,
            'max_files' => 2,
            'max_kilobytes' => 5120,
            'upload_role_ids' => [],
            'verify_role_ids' => [],
            'is_active' => true,
        ];
    }
}
