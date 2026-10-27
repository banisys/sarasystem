<?php

namespace Modules\Student\Filament\Resources\Preregistrations\Pages;

use App\Models\Workflow;
use App\Workflow\WorkflowEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Modules\Student\Filament\Resources\Preregistrations\PreregistrationResource;
use Modules\Student\Models\Preregistration;

class ViewPreregistration extends ViewRecord
{
    protected static string $resource = PreregistrationResource::class;

    public function getTitle(): string
    {
        /** @var Preregistration $preregistration */
        $preregistration = $this->getRecord();

        return "پیش‌ثبت‌نام {$preregistration->fullName()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->startWorkflowAction(),
        ];
    }

    /**
     * For preregistrations without a running case: sent before their stage
     * had a workflow, or whose workflow was inactive (ADR 0016).
     */
    private function startWorkflowAction(): Action
    {
        return Action::make('startWorkflow')
            ->label('شروع فرایند پذیرش')
            ->icon(Heroicon::OutlinedPlay)
            ->visible(fn (Preregistration $record): bool => ! $record->status->isClosed()
                && ! $record->workflowCase?->isInProgress()
                && auth()->user()->can('update', $record))
            ->schema([
                Select::make('workflow_id')
                    ->label('فرایند')
                    ->options(fn (Preregistration $record): array => Workflow::query()->active()->for($record->getMorphClass())->orderBy('name')->pluck('name', 'id')->all())
                    ->default(fn (Preregistration $record): ?int => $record->program?->workflow_id)
                    ->required(),
            ])
            ->action(function (array $data, Preregistration $record, WorkflowEngine $engine): void {
                $workflow = Workflow::query()->active()->for($record->getMorphClass())->findOrFail($data['workflow_id']);
                $engine->start($workflow, $record, auth()->user());

                Notification::make()->title('فرایند پذیرش شروع شد')->success()->send();
                $this->redirect(PreregistrationResource::getUrl('view', ['record' => $record]));
            });
    }
}
