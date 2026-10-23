<?php

namespace Modules\Exam\Filament\Resources\Exams\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Modules\Exam\Filament\Resources\ExamAttempts\ExamAttemptResource;
use Modules\Exam\Filament\Resources\Exams\ExamResource;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('results')
                ->label('نتایج')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('gray')
                ->url(fn (): string => ExamAttemptResource::urlForExam($this->getRecord()))
                ->visible(fn (): bool => ExamAttemptResource::canViewAny()),
            DeleteAction::make()
                ->modalDescription('آزمون با همه‌ی نتایج و پاسخ‌های شرکت‌کنندگان حذف می‌شود.'),
        ];
    }
}
