<?php

namespace App\Support;

use App\Models\User;

/** Read access to class rosters in the currently selected branch. */
interface ClassRoster
{
    public function canBrowse(User $user): bool;

    /** @return array<int, string> Class IDs and titles. */
    public function options(User $user, string $search = ''): array;

    public function title(User $user, int $classId): ?string;

    /** @return list<int>|null Active attendee IDs, or null if the class is unavailable. */
    public function attendeeIds(User $user, int $classId): ?array;
}
