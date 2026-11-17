<?php

namespace Modules\Exam\Support;

use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Filament\Resources\Exams\ExamResource;
use Modules\Exam\Models\Exam;
use Modules\Exam\Services\ExamPaperPreview;

/** Admin controls exposed to session hosts when Exam is enabled. */
class SessionExamAdmin
{
    public function manageAction(string $type, int $id, Closure $canCreate): Action
    {
        $exam = $this->orderedExams($type, $id)->first();

        return Action::make('sessionExam')
            ->label('آزمون جلسه')
            ->icon(Heroicon::OutlinedDocumentText)
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

                $exam = $this->orderedExams($type, $id)->first();

                return $exam === null
                    ? view('exam::filament.exam-paper-preview', ['paper' => ['error' => 'برای این جلسه هنوز آزمونی تعریف نشده است.']])
                    : $preview->savedModal($exam, $classTitle, $startsAt);
            });
    }

    private function orderedExams(string $type, int $id): Builder
    {
        return Exam::query()->where('context_type', $type)->where('context_id', $id)->orderByDesc('is_published')->latest('id');
    }
}
