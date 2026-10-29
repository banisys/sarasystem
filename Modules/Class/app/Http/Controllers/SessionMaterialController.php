<?php

namespace Modules\Class\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\HomeworkSubmission;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Shows a session's material or a learner's homework file. Links are
 * signed and short-lived; they are only handed to people who may see them
 * (ADR 0025, ADR 0030).
 */
class SessionMaterialController extends Controller
{
    public function __invoke(Media $media): BinaryFileResponse
    {
        abort_unless(in_array($media->model_type, [(new ClassSession)->getMorphClass(), (new HomeworkSubmission)->getMorphClass()], true), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
        ]);
    }
}
