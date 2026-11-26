<?php

namespace Modules\Exam\Filament\Resources\Exams\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Modules\Exam\Filament\Resources\ExamAttempts\ExamAttemptResource;
use Modules\Exam\Filament\Resources\Exams\ExamResource;
use Modules\Exam\Models\Exam;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeValidate(): void
    {
        // Attaching an exam to a lesson can change which participant fields are editable.
        $this->getRecord()->refreshForUpdate();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['is_practice']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->userPanelLockAction(true),
            $this->userPanelLockAction(false),
            Action::make('results')
                ->label('نتایج')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('gray')
                ->url(fn (): string => ExamAttemptResource::urlForExam($this->getRecord()))
                ->visible(fn (): bool => ! $this->getRecord()->is_practice && ExamAttemptResource::canViewAny()),
        ];
    }

    private function userPanelLockAction(bool $locked): Action
    {
        return Action::make($locked ? 'lockUserPanel' : 'unlockUserPanel')
            ->label($locked ? 'قفل در پنل کاربری' : 'باز کردن قفل پنل کاربری')
            ->icon($locked ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedLockOpen)
            ->color($locked ? 'gray' : 'warning')
            ->visible(fn (Exam $record): bool => $record->context_type !== null && $record->user_panel_locked !== $locked)
            ->authorize(fn (Exam $record): bool => ExamResource::canEdit($record))
            ->action(function (Exam $record, Action $action) use ($locked): void {
                $record->forceFill(['user_panel_locked' => $locked])->save();
                $action->success();
            })
            ->successNotificationTitle($locked ? 'ویرایش آزمون در پنل کاربری قفل شد.' : 'قفل آزمون در پنل کاربری باز شد.');
    }
}
