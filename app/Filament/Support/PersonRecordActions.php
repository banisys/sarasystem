<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;

/**
 * Actions modules add to a person's row and page in another module's list:
 * a staff member (the Employee module's employees) or a learner (the
 * Student module's learners), e.g. the calendar of their sessions
 * (ADR 0048), so those modules don't depend on theirs. The actions' record
 * is the employee / learner row; the person is its `user`.
 */
class PersonRecordActions
{
    public const STAFF = 'staff';

    public const LEARNER = 'learner';

    /**
     * @var array<string, array<string, Closure(): iterable<Action>>>
     */
    protected static array $factories = [];

    /**
     * Registering a name again replaces it (the app boots once per test).
     *
     * @param  self::STAFF|self::LEARNER  $kind
     * @param  Closure(): iterable<Action>  $callback
     */
    public static function register(string $kind, string $name, Closure $callback): void
    {
        static::$factories[$kind][$name] = $callback;
    }

    /**
     * @param  self::STAFF|self::LEARNER  $kind
     * @return list<Action>
     */
    public static function for(string $kind): array
    {
        return collect(static::$factories[$kind] ?? [])->flatMap(fn (Closure $callback): iterable => $callback())->values()->all();
    }
}
