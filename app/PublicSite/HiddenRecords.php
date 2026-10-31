<?php

namespace App\PublicSite;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Rows the public site must not show to this visitor, e.g. the tenants and
 * programs someone made in a step-by-step tutorial (ADR 0040), which only
 * the person doing it sees. Modules add resolvers; without one nothing is
 * hidden.
 */
class HiddenRecords
{
    /** @var list<Closure(class-string<Model>): list<int|string>> */
    private array $resolvers = [];

    /**
     * @param  Closure(class-string<Model>): list<int|string>  $resolver  keys of the model's hidden rows
     */
    public function hideUsing(Closure $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * @param  class-string<Model>  $model
     * @return list<int|string>
     */
    public function keysOf(string $model): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (Closure $resolver): array => $resolver($model), $this->resolvers ?: [fn (): array => []]))));
    }

    /**
     * Leaves the hidden rows out of a query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function exclude(Builder $query): Builder
    {
        $keys = $this->keysOf($query->getModel()::class);

        return $keys === [] ? $query : $query->whereKeyNot($keys);
    }
}
