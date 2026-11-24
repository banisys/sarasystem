<?php

namespace Modules\Exam\Support;

use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Modules\Exam\Filament\Resources\Exams\ExamResource;
use Modules\Exam\Services\ExamPaperPreview;

/** Admin controls exposed to session hosts when Exam is enabled. */
class SessionExamAdmin
{
    public function manageAction(string $type, int $id, Closure $canCreate): Action
    {
        $exam = app(ExamPaperPreview::class)->sessionExam($type, $id);
        $locked = $exam?->user_panel_locked;

        return Action::make('sessionExam')
            ->label('آزمون جلسه')
            ->icon($locked ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedDocumentText)
            ->tooltip($locked ? 'آزمون در پنل کاربری قفل است' : null)
            ->extraAttributes($locked ? ['aria-label' => 'آزمون جلسه، قفل‌شده در پنل کاربری'] : [])
            ->authorize(fn (): bool => $exam !== null ? ExamResource::canEdit($exam) : $canCreate() && ExamResource::canCreate())
            ->url(fn (): string => $exam !== null
                ? ExamResource::getUrl('edit', ['record' => $exam])
                : ExamResource::getUrl('create-for-context', ['contextType' => $type, 'contextId' => $id]));
    }

    public function paperAction(string $type, int $id, Closure $canRead, ?string $classTitle, CarbonInterface $startsAt): Action
    {
        return Action::make('sessionExams')
            ->label('برگهٔ آزمون جلسه')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('warning')
            ->outlined()
            ->authorize($canRead)
            ->modalHeading('برگهٔ آزمون جلسه')
            ->modalWidth('6xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('بستن')
            ->modalContent(function (ExamPaperPreview $preview) use ($type, $id, $canRead, $classTitle, $startsAt): View {
                abort_unless($canRead(), 403);

                $exam = $preview->sessionExam($type, $id);

                return $exam === null
                    ? view('exam::filament.exam-paper-preview', ['paper' => ['error' => 'برای این جلسه هنوز آزمونی تعریف نشده است.']])
                    : $preview->savedModal($exam, $classTitle, $startsAt);
            });
    }
}
