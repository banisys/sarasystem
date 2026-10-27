<?php

namespace Modules\Employee\Filament\Resources\JobApplications\Pages;

use App\Models\Workflow;
use App\Workflow\WorkflowEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Modules\Employee\Filament\Actions\DownloadResumeAction;
use Modules\Employee\Filament\Resources\JobApplications\JobApplicationResource;
use Modules\Employee\Models\JobApplication;

class ViewJobApplication extends ViewRecord
{
    protected static string $resource = JobApplicationResource::class;

    public function getTitle(): string
    {
        /** @var JobApplication $application */
        $application = $this->getRecord();

        return "درخواست همکاری {$application->fullName()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->startWorkflowAction(),
            DownloadResumeAction::make(),
        ];
    }

    /**
     * For applications without a running case: sent before their position
     * had a workflow, or whose workflow was inactive (ADR 0016).
     */
    private function startWorkflowAction(): Action
    {
        return Action::make('startWorkflow')
            ->label('شروع فرایند استخدام')
            ->icon(Heroicon::OutlinedPlay)
            ->visible(fn (JobApplication $record): bool => ! $record->status->isClosed()
                && ! $record->workflowCase?->isInProgress()
                && auth()->user()->can('update', $record))
            ->schema([
                Select::make('workflow_id')
                    ->label('فرایند')
                    ->options(fn (JobApplication $record): array => Workflow::query()->active()->for($record->getMorphClass())->orderBy('name')->pluck('name', 'id')->all())
                    ->default(fn (JobApplication $record): ?int => $record->jobPosition?->workflow_id)
                    ->required(),
            ])
            ->action(function (array $data, JobApplication $record, WorkflowEngine $engine): void {
                $workflow = Workflow::query()->active()->for($record->getMorphClass())->findOrFail($data['workflow_id']);
                $engine->start($workflow, $record, auth()->user());

                Notification::make()->title('فرایند استخدام شروع شد')->success()->send();
                $this->redirect(JobApplicationResource::getUrl('view', ['record' => $record]));
            });
    }
}
