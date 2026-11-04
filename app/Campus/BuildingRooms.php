<?php

namespace App\Campus;

use App\Models\User;
use Closure;

/**
 * The sources of the 3D building's rooms (ADR 0051). With none registered, or
 * none the user may see, there is no building to show.
 */
class BuildingRooms
{
    public const LIVE = 'live';

    public const TODAY = 'today';

    public const IDLE = 'idle';

    /**
     * @var array<string, Closure(): RoomSource>
     */
    protected static array $sources = [];

    /**
     * Registering a name again replaces it (the app boots once per test).
     *
     * @param  Closure(): RoomSource  $source
     */
    public static function register(string $name, Closure $source): void
    {
        static::$sources[$name] = $source;
    }

    public static function canView(User $user): bool
    {
        return collect(static::$sources)->contains(fn (Closure $source): bool => $source()->canView($user));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function for(User $user): array
    {
        return collect(static::$sources)
            ->map(fn (Closure $source): RoomSource => $source())
            ->filter(fn (RoomSource $source): bool => $source->canView($user))
            ->flatMap(fn (RoomSource $source): array => $source->rooms($user))
            ->values()
            ->all();
    }
}
