<?php

namespace Modules\Exam\Interfaces;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Support\ExamContext;

/** Extension hook: the host owns its records, permissions and participant membership. */
interface ExamContextSource
{
    public function resolve(int $id, User $user): ?ExamContext;

    public function hasTeachingStaff(User $user): bool;

    /** A tenant-scoped, single-column query of contexts this user may participate in. */
    public function participantIds(User $user): Builder;
}
