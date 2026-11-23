<?php

namespace Modules\Exam\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamGroup;
use Modules\Exam\Support\ExamContexts;

class ExamPerformance
{
    public function __construct(private ExamContexts $contexts) {}

    /** @return Builder<ExamGroup> */
    public function groups(User $user): Builder
    {
        return ExamGroup::query()->whereIn('id', $this->eligibleExams($user)->select('exam_group_id'));
    }

    /** @return list<array{key: string, title: string, exams: list<array{id: int, title: string, score: float, total_score: float}>}> */
    public function results(User $user, ExamGroup $group): array
    {
        $exams = $this->eligibleExams($user)
            ->whereBelongsTo($group)
            ->with(['attempts' => fn ($query) => $query->whereBelongsTo($user)->finished()
                ->latest('id')->limit(1)->select('id', 'exam_id', 'score')])
            ->orderByRaw('starts_at desc nulls last')->orderByDesc('id')
            ->get(['id', 'title', 'total_score', 'context_type', 'context_id'])
            ->filter(fn (Exam $exam): bool => $exam->attempts->first()?->score !== null);

        $subjects = $exams->whereNotNull('context_type')->groupBy('context_type')
            ->map(fn ($exams, string $type): array => $this->contexts->subjectsFor($type, $exams->pluck('context_id')->unique()->values()->all()));

        $report = [];
        foreach ($exams as $exam) {
            $subject = $subjects[$exam->context_type][$exam->context_id] ?? null;
            $key = $subject === null ? 'no-subject' : $exam->context_type.':'.$subject['id'];
            $report[$key] ??= ['key' => $key, 'title' => $subject['title'] ?? 'آزمون‌های بدون درس', 'exams' => []];
            $report[$key]['exams'][] = [
                'id' => $exam->id,
                'title' => $exam->title,
                'score' => $exam->attempts->first()->score,
                'total_score' => $exam->total_score,
            ];
        }

        return array_values($report);
    }

    /** @return Builder<Exam> */
    private function eligibleExams(User $user): Builder
    {
        return Exam::query()->published()->forParticipant($user)
            ->where('is_practice', false)
            ->whereIn('result_visibility', [ResultVisibility::Score, ResultVisibility::ScoreWithAnswers]);
    }
}
