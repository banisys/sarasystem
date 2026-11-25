<?php

namespace Modules\Class\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Support\ClassRoster;
use Illuminate\Support\Facades\Gate;
use Modules\Class\Models\Classroom;

class ClassroomRoster implements ClassRoster
{
    public function canBrowse(User $user): bool
    {
        return Tenant::current() !== null && Gate::forUser($user)->allows('viewAny', Classroom::class);
    }

    public function options(User $user, string $search = ''): array
    {
        if (! $this->canBrowse($user)) {
            return [];
        }

        return Classroom::query()->whereLike('title', '%'.$search.'%')
            ->orderBy('title')->orderBy('id')->limit(50)->pluck('title', 'id')->all();
    }

    public function title(User $user, int $classId): ?string
    {
        return $this->viewableClass($user, $classId)?->title;
    }

    public function attendeeIds(User $user, int $classId): ?array
    {
        return $this->viewableClass($user, $classId)?->members()
            ->activeToday()->attendees()->distinct()->orderBy('user_id')->pluck('user_id')->all();
    }

    private function viewableClass(User $user, int $classId): ?Classroom
    {
        if (! $this->canBrowse($user)) {
            return null;
        }

        $class = Classroom::query()->find($classId);

        return $class !== null && Gate::forUser($user)->allows('view', $class) ? $class : null;
    }
}
