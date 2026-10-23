<?php

namespace Modules\Exam\Http\Controllers\Concerns;

use App\Models\User;
use Modules\Exam\Models\Exam;

trait ChecksParticipant
{
    /**
     * Others' and unpublished exams are "not found", so their existence
     * isn't revealed. Route binding already limits {exam} to the current
     * tenant (BelongsToTenant).
     */
    protected function ensureParticipant(Exam $exam, User $user): void
    {
        abort_unless($exam->is_published && $exam->hasParticipant($user), 404);
    }
}
