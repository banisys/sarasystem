<?php

namespace Modules\Class\Services;

use App\Campus\BuildingRooms;
use App\Campus\RoomSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;

/**
 * The current tenant's running classes as rooms of the 3D building (ADR 0051),
 * with what happens in them today. Sub-classes share their class's room.
 */
class ClassroomRooms implements RoomSource
{
    public function canView(User $user): bool
    {
        return $user->can('viewAny', Classroom::class);
    }

    public function rooms(User $user): array
    {
        $classrooms = Classroom::query()
            ->topLevel()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->orderBy('title')
            ->get();

        $ids = $classrooms->modelKeys();
        $sessions = ClassSession::query()
            ->whereIn('classroom_id', $ids)
            ->takingPlace()
            ->whereBetween('starts_at', [today(), today()->endOfDay()])
            ->with(['subject', 'teacher', 'substituteTeacher'])
            ->orderBy('starts_at')
            ->get()
            ->groupBy('classroom_id');
        $people = ClassMember::query()
            ->whereIn('classroom_id', $ids)
            ->current()
            ->attendees()
            ->toBase()
            ->selectRaw('classroom_id, count(*) as total')
            ->groupBy('classroom_id')
            ->pluck('total', 'classroom_id');
        $canOpen = $classrooms->isNotEmpty() && $user->can('view', $classrooms->first());

        return $classrooms->map(function (Classroom $classroom) use ($sessions, $people, $canOpen): array {
            /** @var Collection<int, ClassSession> $today */
            $today = $sessions[$classroom->getKey()] ?? collect();
            $live = $today->first(fn (ClassSession $session): bool => $session->starts_at->lessThanOrEqualTo(now()) && $session->ends_at->greaterThan(now()));
            $next = $today->first(fn (ClassSession $session): bool => $session->starts_at->greaterThan(now()));

            return [
                'id' => $classroom->getKey(),
                'title' => $classroom->title,
                'online' => $classroom->mode === ClassMode::Online,
                'mode' => $classroom->mode->getLabel(),
                'state' => match (true) {
                    $live !== null => BuildingRooms::LIVE,
                    $next !== null => BuildingRooms::TODAY,
                    default => BuildingRooms::IDLE,
                },
                'people' => (int) ($people[$classroom->getKey()] ?? 0),
                'capacity' => $classroom->capacity,
                'url' => $canOpen ? ClassroomResource::getUrl('sessions', ['record' => $classroom]) : null,
                'live' => $live === null ? null : [
                    'subject' => $live->subject?->title,
                    'teacher' => ($live->substituteTeacher ?? $live->teacher)?->name,
                    'time' => $live->starts_at->format('H:i').'–'.$live->ends_at->format('H:i'),
                    'url' => $canOpen ? ClassSessionResource::getUrl('view', ['record' => $live]) : null,
                ],
                'next' => $next?->starts_at->format('H:i'),
            ];
        })->values()->all();
    }
}
