<?php

namespace Modules\Tutorial\Scenarios;

/**
 * A tutorial for one kind of client, e.g. a language institute (ADR 0040):
 * chapters of missions, done in order.
 */
abstract class Scenario
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * @return array<string, list<Mission>> chapter title => missions
     */
    abstract public function chapters(): array;

    /**
     * @return list<Mission>
     */
    public function missions(): array
    {
        return array_merge(...array_values($this->chapters()));
    }

    public function mission(string $key): ?Mission
    {
        foreach ($this->missions() as $mission) {
            if ($mission->key === $key) {
                return $mission;
            }
        }

        return null;
    }
}
