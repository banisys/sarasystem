<?php

namespace Modules\Class\Support;

use Closure;

/**
 * What other modules add to classes without Class depending on them
 * (ADR 0031), e.g. the School module's «پایه / رشته»: fields of the class
 * form (they load and save themselves with
 * loadStateFromRelationshipsUsing() / saveRelationshipsUsing()), entries of
 * the class page, and columns and filters of the classes list. Each is a
 * closure returning components, so they are built when the screen is.
 */
class ClassroomExtensions
{
    /**
     * @var array<string, array{form?: Closure(): list<mixed>, infolist?: Closure(): list<mixed>, columns?: Closure(): list<mixed>, filters?: Closure(): list<mixed>}>
     */
    protected static array $extensions = [];

    /**
     * Registering a name again replaces it (the app boots once per test).
     *
     * @param  array{form?: Closure(): list<mixed>, infolist?: Closure(): list<mixed>, columns?: Closure(): list<mixed>, filters?: Closure(): list<mixed>}  $parts
     */
    public static function register(string $name, array $parts): void
    {
        static::$extensions[$name] = $parts;
    }

    /**
     * @param  'form'|'infolist'|'columns'|'filters'  $part
     * @return list<mixed>
     */
    public static function components(string $part): array
    {
        return collect(static::$extensions)
            ->flatMap(fn (array $parts): array => isset($parts[$part]) ? ($parts[$part])() : [])
            ->values()
            ->all();
    }
}
