<?php

namespace App\CustomFields;

use Closure;

class AdditionalBuiltInFields
{
    /**
     * @var array<string, array<string, Closure(): list<BuiltInField>>>
     */
    private static array $providers = [];

    /**
     * Modules extend another module's form without depending on it.
     *
     * @param  Closure(): list<BuiltInField>  $fields
     */
    public static function register(string $form, string $key, Closure $fields): void
    {
        static::$providers[$form][$key] = $fields;
    }

    /**
     * @return list<BuiltInField>
     */
    public static function for(string $form): array
    {
        return collect(static::$providers[$form] ?? [])->flatMap(fn (Closure $provider): array => $provider())->values()->all();
    }
}
