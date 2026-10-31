<?php

namespace Modules\Tutorial\Scenarios;

/**
 * The tutorial scenarios to choose from (ADR 0040).
 */
class Scenarios
{
    /** @var array<string, class-string<Scenario>> */
    private array $scenarios = [];

    /**
     * @param  class-string<Scenario>  $scenario
     */
    public function register(string $scenario): void
    {
        $this->scenarios[app($scenario)->key()] = $scenario;
    }

    /**
     * @return array<string, Scenario>
     */
    public function all(): array
    {
        return array_map(fn (string $scenario): Scenario => app($scenario), $this->scenarios);
    }

    public function find(string $key): ?Scenario
    {
        return isset($this->scenarios[$key]) ? app($this->scenarios[$key]) : null;
    }
}
