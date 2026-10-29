<?php

namespace App\Filament\Support;

use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Relation managers modules add to another module's records, e.g. the
 * contracts and documents of an employee (ADR 0020, ADR 0019), so the
 * record's module doesn't depend on theirs. The record's resource returns
 * `for(Model::class)` from getRelations(); its view page shows them as tabs.
 * The relationship itself is added with Model::resolveRelationUsing().
 */
class ModelRelationManagers
{
    /**
     * @var array<class-string<Model>, array<class-string<RelationManager>, class-string<RelationManager>>>
     */
    protected static array $managers = [];

    /**
     * Registering a manager again keeps one copy (the app boots once per test).
     *
     * @param  class-string<Model>  $model
     * @param  class-string<RelationManager>  $manager
     */
    public static function register(string $model, string $manager): void
    {
        static::$managers[$model][$manager] = $manager;
    }

    /**
     * @param  class-string<Model>  $model
     * @return list<class-string<RelationManager>>
     */
    public static function for(string $model): array
    {
        return array_values(static::$managers[$model] ?? []);
    }
}
