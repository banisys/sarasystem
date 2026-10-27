<?php

namespace App\Workflow;

use App\Models\WorkflowCase;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * Work a module adds to workflow steps (ADR 0016, ADR 0019), e.g. the
 * documents to upload or check in a step. It adds settings to the step
 * designer (stored in the step under `extensions.{key}`), actions and
 * sections to the cartable, and may refuse approving a step whose work
 * is not done. Modules register theirs with WorkflowStepExtensions.
 */
abstract class StepExtension
{
    abstract public function key(): string;

    /**
     * Fields in each step of the designer. Their state is the step's
     * `extensions.{key}`; the workflow's `subject_type` is `../../../subject_type`.
     *
     * @return array<Component|Field>
     */
    public function designerFields(): array
    {
        return [];
    }

    /**
     * Lines shown for the step in the workflow diagram.
     *
     * @return list<string>
     */
    public function summary(WorkflowStep $step, string $subjectType): array
    {
        return [];
    }

    /**
     * Header actions in the cartable; their record is the WorkflowCase.
     *
     * @return list<Action>
     */
    public function caseActions(): array
    {
        return [];
    }

    /**
     * Sections shown in the cartable under the subject; their record is the
     * WorkflowCase.
     *
     * @return list<Component>
     */
    public function caseDetails(): array
    {
        return [];
    }

    /**
     * Called before the current step is approved, inside the engine's
     * transaction; throw WorkflowActionDenied to refuse.
     */
    public function beforeApprove(WorkflowCase $case, WorkflowStep $step): void {}
}
