<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;

/**
 * Actions modules add to a staff member's row and page (the Employee
 * module's employees), e.g. the calendar of the sessions they teach
 * (ADR 0048), so the staff module doesn't depend on theirs. The actions'
 * record is the staff row; the person is its `user`.
 */
class StaffRecordActions
{
    /**
     * @var array<string, Closure(): iterable<Action>>
     */
    protected static array $factories = [];

    /**
     * Registering a name again replaces it (the app boots once per test).
     *
     * @param  Closure(): iterable<Action>  $callback
     */
    public static function register(string $name, Closure $callback): void
    {
        static::$factories[$name] = $callback;
    }

    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return collect(static::$factories)->flatMap(fn (Closure $callback): iterable => $callback())->values()->all();
    }
}
