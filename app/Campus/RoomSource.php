<?php

namespace App\Campus;

use App\Models\User;

/**
 * Gives the 3D building its rooms (ADR 0051): the Class module's classes, so
 * the Campus module doesn't depend on it.
 */
interface RoomSource
{
    public function canView(User $user): bool;

    /**
     * The current tenant's rooms.
     *
     * @return list<array{
     *     id: int,
     *     title: string,
     *     online: bool,
     *     mode: string,
     *     state: BuildingRooms::LIVE|BuildingRooms::TODAY|BuildingRooms::IDLE,
     *     people: int,
     *     capacity: int|null,
     *     url: string|null,
     *     live: array{subject: string|null, teacher: string|null, time: string, url: string|null}|null,
     *     next: string|null,
     * }>
     */
    public function rooms(User $user): array;
}
