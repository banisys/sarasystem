<?php

namespace Modules\Exam\Services;

use Generator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Exam\Models\ExamSection;
use Modules\Exam\Models\Question;

class ExamSectionDraw
{
    /**
     * @param  array<ExamSection>|Collection<int, ExamSection>  $sections
     * @return Generator<int, array{ExamSection, Collection<int, Question>}>
     */
    public function draw(array|Collection $sections, bool $random = false): Generator
    {
        // A draw from an overlapping bank must leave explicit selections available.
        $usedIds = [];
        foreach ($sections as $section) {
            $usedIds = [...$usedIds, ...($section->question_ids ?? [])];
        }

        foreach ($sections as $section) {
            $candidates = $section->questionBank?->questions()->active();
            $questions = new Collection;

            if ($candidates !== null && $section->question_count > 0) {
                if ($section->question_ids === null) {
                    $candidates->whereNotIn('exam_questions.id', $usedIds);
                } else {
                    $candidates->whereKey($section->question_ids);
                }

                $questions = $candidates
                    ->when($random, fn ($query) => $query->inRandomOrder(), fn ($query) => $query->orderBy('exam_questions.id'))
                    ->limit($section->question_count)->get();
            }

            $usedIds = [...$usedIds, ...$questions->modelKeys()];

            yield [$section, $questions];
        }
    }
}
