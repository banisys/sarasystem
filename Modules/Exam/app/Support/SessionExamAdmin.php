<?php

namespace Modules\Exam\Support;

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
    public function withExamAvailability(Builder $sessions, string $type): Builder
    {
        return $sessions->addSelect([
            'has_session_exam' => Exam::query()->where('context_type', $type)
                ->whereColumn('context_id', $sessions->getModel()->qualifyColumn('id'))
                ->selectRaw('1')->limit(1),
        ]);
    }

    public function paperAction(string $type, int $id, Closure $canRead): Action
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
            ->modalContent(function (ExamPaperPreview $preview) use ($type, $id, $canRead): View {
                abort_unless($canRead(), 403);

                $exam = Exam::query()->where('context_type', $type)->where('context_id', $id)
                    ->orderByDesc('is_published')->latest('id')->first();

                return view('exam::filament.exam-paper-preview', [
                    'paper' => $exam === null
                        ? ['error' => 'برای این جلسه هنوز آزمونی تعریف نشده است.']
                        : $preview->forSaved($exam),
                    'notice' => $exam?->question_snapshot === null
                        ? 'این برگه نمونه‌ای از سؤال‌های بانک آزمون است. سؤال‌ها یا ترتیب آن‌ها در آزمون آنلاینِ تصادفی ممکن است متفاوت باشند.'
                        : null,
                ]);
            });
    }
}
