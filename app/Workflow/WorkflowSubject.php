<?php

namespace App\Workflow;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowCase;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of record that can go through a workflow, e.g. a job application.
 * A module describes its records here and registers the description with
 * WorkflowSubjects, since the core does not know module models (ADR 0005,
 * ADR 0016). The hooks run inside the engine's transaction; throwing
 * WorkflowActionDenied undoes the action.
 *
 * @template TModel of Model
 */
abstract class WorkflowSubject
{
    /**
     * @return class-string<TModel>
     */
    abstract public function model(): string;

    /**
     * What the records are called, e.g. «درخواست همکاری».
     */
    abstract public function label(): string;

    /**
     * @param  TModel  $subject
     */
    abstract public function title(Model $subject): string;

    /**
     * Infolist components showing the record in the cartable.
     *
     * @return array<Component|Entry>
     */
    abstract public function details(): array;

    /**
     * The morph class stored in workflows.subject_type.
     */
    public function type(): string
    {
        return (new ($this->model()))->getMorphClass();
    }

    /**
     * What happens when the last step is approved, in one sentence; shown
     * at the end of the workflow diagram.
     */
    public function completionSummary(): ?string
    {
        return null;
    }

    /**
     * Whether workflows for these records can be designed in the tenant.
     */
    public function availableIn(Tenant $tenant): bool
    {
        return true;
    }

    /**
     * Header actions shown next to the workflow actions in the cartable,
     * e.g. downloading a file of the record.
     *
     * @return list<Action>
     */
    public function extraActions(): array
    {
        return [];
    }

    /**
     * Form fields for the «ویرایش اطلاعات» step action; none means the
     * records can't be edited in a workflow.
     *
     * @return array<Component|Field>
     */
    public function editForm(): array
    {
        return [];
    }

    /**
     * @param  TModel  $subject
     * @param  array<string, mixed>  $data
     */
    public function saveEdit(Model $subject, array $data): void
    {
        $subject->fill($data)->save();
    }

    /**
     * Label of a changed attribute in the case history.
     */
    public function attributeLabel(string $attribute): string
    {
        return $attribute;
    }

    /**
     * Extra fields asked when the last step is approved (e.g. where a new
     * employee works); their values reach completed().
     *
     * @param  TModel  $subject
     * @return array<Component|Field>
     */
    public function completionForm(Model $subject): array
    {
        return [];
    }

    /**
     * The case entered another step (also when it starts).
     */
    public function moved(WorkflowCase $case): void {}

    /**
     * The last step was approved.
     *
     * @param  array<string, mixed>  $data  values of completionForm()
     */
    public function completed(WorkflowCase $case, User $actor, array $data): void {}

    public function rejected(WorkflowCase $case, User $actor): void {}
}
