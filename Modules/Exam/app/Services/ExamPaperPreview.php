<?php

namespace Modules\Exam\Services;

use Illuminate\Support\Facades\Validator;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\QuestionBank;

class ExamPaperPreview
{
    public function forSaved(Exam $exam): array
    {
        if ($exam->question_snapshot === null) {
            return $this->forDraft([
                'title' => $exam->title,
                'description' => $exam->description,
                'duration_minutes' => $exam->duration_minutes,
                'sections' => $exam->sections()->get(['question_bank_id', 'question_count'])->toArray(),
            ], $exam->title);
        }

        return [
            'title' => $exam->title,
            'description' => $exam->description ?? '',
            'duration' => $exam->duration_minutes,
            'questions' => array_map(fn (array $question): array => $this->question(
                $question['body'], $question['options'] ?? [], QuestionType::tryFrom($question['type']),
            ), $exam->question_snapshot),
            'error' => $exam->question_snapshot === [] ? 'این آزمون سؤالی برای چاپ ندارد.' : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{title: string, description: string, duration: int|null, questions: list<array{body: string, options: list<string>, written: bool}>, error: string|null}
     */
    public function forDraft(array $draft, string $sessionTitle): array
    {
        $validator = Validator::make($draft, [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'array'],
            'sections.*.question_bank_id' => ['required', 'integer', 'min:1'],
            'sections.*.question_count' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        $paper = ['title' => $sessionTitle, 'description' => '', 'duration' => null, 'questions' => [], 'error' => null];

        if ($validator->fails()) {
            return array_replace($paper, ['error' => 'برای نمایش نسخهٔ آزمون، بانک سؤال و تعداد سؤال‌های هر ردیف را وارد کنید و مشخصات آزمون را بررسی کنید.']);
        }

        $data = $validator->validated();
        $paper['title'] = trim($data['title'] ?? '') ?: $sessionTitle;
        $paper['description'] = $data['description'] ?? '';
        $paper['duration'] = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null;
        $banks = QuestionBank::query()->whereKey(array_column($data['sections'], 'question_bank_id'))->get()->keyBy('id');
        $usedIds = [];

        foreach ($data['sections'] as $section) {
            $bank = $banks->get($section['question_bank_id']);

            if ($bank === null) {
                return array_replace($paper, ['questions' => [], 'error' => 'یکی از بانک‌های انتخاب‌شده در دسترس نیست.']);
            }

            $questions = $bank->questions()->active()
                ->whereNotIn('exam_questions.id', $usedIds)
                ->orderBy('exam_questions.id')
                ->limit($section['question_count'])
                ->get(['exam_questions.id', 'exam_questions.type', 'exam_questions.body', 'exam_questions.options']);

            if ($questions->count() < (int) $section['question_count']) {
                return array_replace($paper, ['questions' => [], 'error' => "بانک «{$bank->title}» به تعداد درخواستی سؤال فعال و غیرتکراری ندارد."]);
            }

            foreach ($questions as $question) {
                $usedIds[] = $question->getKey();
                $paper['questions'][] = $this->question($question->body, $question->options ?? [], $question->type);
            }
        }

        return $paper;
    }

    private function question(string $body, array $options, ?QuestionType $type): array
    {
        return [
            'body' => $body,
            'options' => array_values(array_column($options, 'text')),
            'written' => $type === QuestionType::Descriptive,
        ];
    }
}
