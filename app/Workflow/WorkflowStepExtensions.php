<?php

namespace App\Workflow;

/**
 * Step extensions registered by modules (ADR 0019).
 */
class WorkflowStepExtensions
{
    /**
     * @var array<string, StepExtension>
     */
    protected static array $extensions = [];

    /**
     * Registering a key again replaces it (the app boots once per test).
     */
    public static function register(StepExtension $extension): void
    {
        static::$extensions[$extension->key()] = $extension;
    }

    /**
     * @return array<string, StepExtension>
     */
    public static function all(): array
    {
        return static::$extensions;
    }
}
