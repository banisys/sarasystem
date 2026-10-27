<?php

namespace Modules\Document\Tests\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;
use Modules\Document\Services\Documents;

trait MakesDocuments
{
    protected function documentType(Tenant $primary, array $attributes = []): DocumentType
    {
        return DocumentType::factory()->for($primary)->create($attributes);
    }

    protected static function image(string $name = 'scan.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 400);
    }

    protected function uploadFor(Model $record, DocumentType $type, ?string $expiresAt = null): Document
    {
        return app(Documents::class)->upload($record, $type, [self::image()], $expiresAt, null);
    }
}
