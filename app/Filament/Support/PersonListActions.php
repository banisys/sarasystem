<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;

/**
 * Lets a module add buttons to the header of a list of people (ADR 0058), as
 * PersonRecordActions does for their rows, without the list knowing the module.
 */
class PersonListActions
{
    /**
     * @var array<string, array<string, Closure(): iterable<Action>>>
     */
    protected static array $factories = [];

    /**
     * Registering a name again replaces it (the app boots once per test).
     *
     * @param  PersonRecordActions::STAFF|PersonRecordActions::LEARNER  $kind
     * @param  Closure(): iterable<Action>  $callback
     */
    public static function register(string $kind, string $name, Closure $callback): void
    {
        static::$factories[$kind][$name] = $callback;
    }

    /**
     * @param  PersonRecordActions::STAFF|PersonRecordActions::LEARNER  $kind
     * @return list<Action>
     */
    public static function for(string $kind): array
    {
        return collect(static::$factories[$kind] ?? [])->flatMap(fn (Closure $callback): iterable => $callback())->values()->all();
    }
}
