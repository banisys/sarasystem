<?php

namespace App\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The kinds of people who have documents, registered by modules (ADR 0019).
 */
class DocumentHolders
{
    /**
     * @var array<string, DocumentHolder>
     */
    protected static array $holders = [];

    /**
     * Registering a key again replaces it (the app boots once per test).
     */
    public static function register(DocumentHolder $holder): void
    {
        static::$holders[$holder->key()] = $holder;
    }

    /**
     * @return array<string, DocumentHolder>
     */
    public static function all(): array
    {
        return static::$holders;
    }

    public static function find(?string $key): ?DocumentHolder
    {
        return static::$holders[$key] ?? null;
    }

    /**
     * @return array<string, string> key => label
     */
    public static function options(): array
    {
        return array_map(fn (DocumentHolder $holder): string => $holder->label(), static::$holders);
    }

    /**
     * The holder whose applicant or member records are of this morph type.
     */
    public static function forType(string $morphType): ?DocumentHolder
    {
        $class = Relation::getMorphedModel($morphType) ?? $morphType;

        foreach (static::$holders as $holder) {
            if (in_array($class, [$holder->applicantModel(), $holder->memberModel()], true)) {
                return $holder;
            }
        }

        return null;
    }

    public static function forRecord(Model $record): ?DocumentHolder
    {
        return static::forType($record->getMorphClass());
    }

    /**
     * `morph_type:id`, for selects that mix applicants and members.
     */
    public static function recordKey(Model $record): string
    {
        return $record->getMorphClass().':'.$record->getKey();
    }
}
