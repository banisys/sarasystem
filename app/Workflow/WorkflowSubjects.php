<?php

namespace App\Workflow;

use Illuminate\Database\Eloquent\Model;

/**
 * The kinds of records workflows can run on, registered by modules
 * (ADR 0016).
 */
class WorkflowSubjects
{
    /**
     * @var array<string, WorkflowSubject<Model>>
     */
    protected static array $subjects = [];

    /**
     * Registering a type again replaces it (the app boots once per test).
     *
     * @param  WorkflowSubject<Model>  $subject
     */
    public static function register(WorkflowSubject $subject): void
    {
        static::$subjects[$subject->type()] = $subject;
    }

    /**
     * @return array<string, WorkflowSubject<Model>>
     */
    public static function all(): array
    {
        return static::$subjects;
    }

    /**
     * @return WorkflowSubject<Model>|null
     */
    public static function find(string $type): ?WorkflowSubject
    {
        return static::$subjects[$type] ?? null;
    }

    /**
     * @return WorkflowSubject<Model>
     */
    public static function for(string $type): WorkflowSubject
    {
        return static::find($type) ?? throw new WorkflowActionDenied('این نوع پرونده در سامانه تعریف نشده است.');
    }
}
