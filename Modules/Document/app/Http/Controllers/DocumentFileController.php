<?php

namespace Modules\Document\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Document\Models\Document;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Shows a document's file. Links are signed and short-lived; they are only
 * handed to people allowed to see the document (ADR 0019).
 */
class DocumentFileController extends Controller
{
    public function __invoke(Media $media): BinaryFileResponse
    {
        abort_unless($media->model_type === (new Document)->getMorphClass(), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
        ]);
    }
}
