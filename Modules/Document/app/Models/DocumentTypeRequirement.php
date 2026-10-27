<?php

namespace Modules\Document\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A requirement owner (e.g. a job position) a document type is required
 * for (ADR 0019). Read through its type, which belongs to a tenant.
 */
#[Fillable(['document_type_id', 'holder', 'owner_id'])]
class DocumentTypeRequirement extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
        ];
    }
}
