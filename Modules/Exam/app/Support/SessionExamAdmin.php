<?php

namespace Modules\Exam\Support;

use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Models\Exam;
use Modules\Exam\Services\ExamPaperPreview;

/** Admin controls exposed to session hosts when Exam is enabled. */
class SessionExamAdmin
{
    public function withSessionExamId(Builder $sessions, string $type): Builder
    {
        return $sessions->addSelect([
            'session_exam_id' => $this->orderedExams($type)
                ->whereColumn('context_id', $sessions->getModel()->qualifyColumn('id'))
                ->select('exams.id')->limit(1),
        ]);
    }

    public function paperAction(string $type, int $id, Closure $canRead, ?string $classTitle, CarbonInterface $startsAt): Action
    {
        return Action::make('sessionExams')
            ->label('آزمون جلسه')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('warning')
            ->outlined()
            ->authorize($canRead)
            ->modalHeading('آزمون جلسه')
            ->modalWidth('6xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('بستن')
            ->modalContent(function (ExamPaperPreview $preview) use ($type, $id, $canRead, $classTitle, $startsAt): View {
                abort_unless($canRead(), 403);

                $exam = $this->orderedExams($type)->where('context_id', $id)->first();

                return $exam === null
                    ? view('exam::filament.exam-paper-preview', ['paper' => ['error' => 'برای این جلسه هنوز آزمونی تعریف نشده است.']])
                    : $preview->savedModal($exam, $classTitle, $startsAt);
            });
    }

    private function orderedExams(string $type): Builder
    {
        return Exam::query()->where('context_type', $type)->orderByDesc('is_published')->latest('id');
    }
}
