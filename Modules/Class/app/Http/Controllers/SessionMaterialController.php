<?php

namespace Modules\Class\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Class\Models\ClassSession;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Shows a session's material. Links are signed and short-lived; they are
 * only handed to people who may see the session (ADR 0025).
 */
class SessionMaterialController extends Controller
{
    public function __invoke(Media $media): BinaryFileResponse
    {
        abort_unless($media->model_type === (new ClassSession)->getMorphClass(), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
        ]);
    }
}
