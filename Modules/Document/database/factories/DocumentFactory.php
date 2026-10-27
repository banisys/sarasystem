<?php

namespace Modules\Document\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Models\Document;

/**
 * Give it a documentable, tenant, holder and type: `Documents::upload()`
 * is the usual way to make one with files.
 *
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => DocumentStatus::Pending,
            'uploaded_at' => now(),
        ];
    }
}
