<?php

namespace Modules\Exam\Interfaces;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Support\ExamContext;

/** Extension hook: the host owns its records, permissions and participant membership. */
interface ExamContextSource
{
    public function resolve(int $id, User $user): ?ExamContext;

    /** Locate the storage tenant for a direct visit by a user authorized by this host. */
    public function tenantForVisit(int $id, User $user): ?Tenant;

    public function hasTeachingStaff(User $user): bool;

    /** A single-column query of contexts this user may participate in under the host’s access rules. */
    public function participantIds(User $user): Builder;

    /**
     * Subjects for contexts already authorized through participantIds.
     *
     * @param  list<int>  $contextIds
     * @return array<int, array{id: int, title: string}> Keyed by context ID.
     */
    public function subjectsFor(array $contextIds): array;
}
