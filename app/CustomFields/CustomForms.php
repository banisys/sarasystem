<?php

namespace App\CustomFields;

/**
 * The customizable forms, registered by modules (ADR 0017).
 */
class CustomForms
{
    /**
     * @var array<string, CustomizableForm>
     */
    protected static array $forms = [];

    /**
     * Registering a key again replaces it (the app boots once per test).
     */
    public static function register(CustomizableForm $form): void
    {
        static::$forms[$form->key()] = $form;
    }

    /**
     * @return array<string, CustomizableForm>
     */
    public static function all(): array
    {
        return static::$forms;
    }

    public static function find(string $key): ?CustomizableForm
    {
        return static::$forms[$key] ?? null;
    }
}
