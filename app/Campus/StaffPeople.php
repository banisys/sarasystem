<?php

namespace App\Campus;

use App\Models\User;
use Closure;

/**
 * The sources of the staff pyramid's people (ADR 0058), so that the 3D views and
 * the staff module know nothing of each other. With none registered, or none the
 * user may see, there is nothing to show.
 */
class StaffPeople
{
    /**
     * @var array<string, Closure(): StaffSource>
     */
    protected static array $sources = [];

    /**
     * Registering a name again replaces it (the app boots once per test).
     *
     * @param  Closure(): StaffSource  $source
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
     * @return list<array{user_id: int, name: string, title: ?string, url: ?string}>
     */
    public static function for(User $user): array
    {
        return collect(static::$sources)
            ->map(fn (Closure $source): StaffSource => $source())
            ->filter(fn (StaffSource $source): bool => $source->canView($user))
            ->flatMap(fn (StaffSource $source): array => $source->people($user))
            ->unique('user_id')
            ->values()
            ->all();
    }
}
