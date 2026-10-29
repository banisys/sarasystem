<?php

namespace App\Contract;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The kinds of people who sign contracts, registered by modules (ADR 0020).
 */
class ContractParties
{
    /**
     * @var array<string, ContractParty>
     */
    protected static array $parties = [];

    /**
     * Registering a key again replaces it (the app boots once per test).
     */
    public static function register(ContractParty $party): void
    {
        static::$parties[$party->key()] = $party;
    }

    /**
     * @return array<string, ContractParty>
     */
    public static function all(): array
    {
        return static::$parties;
    }

    public static function find(?string $key): ?ContractParty
    {
        return static::$parties[$key] ?? null;
    }

    /**
     * @return array<string, string> key => label
     */
    public static function options(): array
    {
        return array_map(fn (ContractParty $party): string => $party->label(), static::$parties);
    }

    /**
     * The party whose applicant or member records are of this morph type.
     */
    public static function forType(string $morphType): ?ContractParty
    {
        $class = Relation::getMorphedModel($morphType) ?? $morphType;

        foreach (static::$parties as $party) {
            if (in_array($class, [$party->applicantModel(), $party->memberModel()], true)) {
                return $party;
            }
        }

        return null;
    }

    public static function forRecord(Model $record): ?ContractParty
    {
        return static::forType($record->getMorphClass());
    }
}
