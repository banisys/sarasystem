<?php

namespace Modules\Exam\Filament\Resources\ExamAttempts\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Exam\Filament\Resources\ExamAttempts\ExamAttemptResource;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Services\ExamTaking;

/**
 * An attempt's answers; written answers get their score here. Saving sums
 * the attempt's score again.
 */
class GradeExamAttempt extends EditRecord
{
    protected static string $resource = ExamAttemptResource::class;

    protected static ?string $title = 'پاسخ‌ها و تصحیح';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var ExamAttempt $attempt */
        $attempt = $this->getRecord();

        if ($attempt->isInProgress() && $attempt->isOverdue()) {
            app(ExamTaking::class)->finish($attempt, timedOut: true);
            $this->fillForm();
        }
    }

    protected function afterSave(): void
    {
        /** @var ExamAttempt $attempt */
        $attempt = $this->getRecord();

        if (! $attempt->isInProgress()) {
            $attempt->recalculate();
        }
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'نمره‌ها ثبت شد';
    }
}
