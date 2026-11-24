<?php

namespace Modules\Exam\Services;

use App\Models\Tenant;
use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamSection;
use Modules\Exam\Models\QuestionBank;

class ExamPaperPreview
{
    public function savedModal(Exam $exam, ?string $classTitle, ?CarbonInterface $startsAt): View
    {
        return view('exam::filament.exam-paper-preview', $this->savedPreview($exam, $classTitle, $startsAt));
    }

    public function sessionExam(string $type, int $id): ?Exam
    {
        return Exam::query()->where('context_type', $type)->where('context_id', $id)
            ->orderByDesc('is_published')->latest('id')->first();
    }

    public function savedPreview(Exam $exam, ?string $classTitle, ?CarbonInterface $startsAt): array
    {
        return [
            'paper' => $this->forSaved($exam, $classTitle, $startsAt),
            'notice' => $exam->question_snapshot === null
                ? 'برگه از سؤال‌های گلچین‌شده و نمونه‌ای از ردیف‌های خودکار تهیه می‌شود. برداشت ردیف‌های خودکار و ترتیب سؤال‌ها در آزمون آنلاین ممکن است متفاوت باشد.'
                : null,
        ];
    }

    public function forSaved(Exam $exam, ?string $classTitle, ?CarbonInterface $startsAt): array
    {
        $startsAt ??= $exam->starts_at;

        if ($exam->question_snapshot === null) {
            return $this->forDraft([
                'title' => $exam->title,
                'description' => $exam->description,
                'duration_minutes' => $exam->duration_minutes,
                'is_practice' => $exam->is_practice,
                'sections' => $exam->sections()->get(['question_bank_id', 'question_count', 'question_ids'])->toArray(),
            ], $exam->title, $classTitle, $startsAt);
        }

        return array_replace($this->paper($exam->title, $classTitle, $startsAt), [
            'description' => $exam->description ?? '',
            'duration' => $exam->duration_minutes,
            'questions' => array_map(fn (array $question): array => $this->question(
                $question['body'], $question['options'] ?? [], QuestionType::tryFrom($question['type']),
                ! $exam->is_practice && isset($question['score']) ? (float) $question['score'] : null,
            ), $exam->question_snapshot),
            'error' => $exam->question_snapshot === [] ? 'این آزمون سؤالی برای چاپ ندارد.' : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{title: string, branch: string|null, class: string|null, date: string|null, time: string|null, description: string, duration: int|null, questions: list<array{body: string, options: list<string>, written: bool, score: float|null}>, error: string|null}
     */
    public function forDraft(array $draft, string $sessionTitle, ?string $classTitle, ?CarbonInterface $startsAt): array
    {
        $validator = Validator::make($draft, [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'is_practice' => ['sometimes', 'boolean'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'array'],
            'sections.*.question_bank_id' => ['required', 'integer', 'min:1'],
            'sections.*.question_count' => ['required', 'integer', 'min:1', 'max:500'],
            'sections.*.question_ids' => ['nullable', 'array', 'list', 'min:1', 'max:500'],
            'sections.*.question_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $paper = $this->paper($sessionTitle, $classTitle, $startsAt);

        if ($validator->fails()) {
            return array_replace($paper, ['error' => 'برای نمایش نسخهٔ آزمون، بانک سؤال و تعداد سؤال‌های هر ردیف را وارد کنید و مشخصات آزمون را بررسی کنید.']);
        }

        $data = $validator->validated();
        $paper['title'] = trim($data['title'] ?? '') ?: $sessionTitle;
        $paper['description'] = $data['description'] ?? '';
        $paper['duration'] = isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null;
        $banks = QuestionBank::query()->whereKey(array_column($data['sections'], 'question_bank_id'))->get()->keyBy('id');
        $sections = array_map(fn (array $section): ExamSection => (new ExamSection($section))
            ->setRelation('questionBank', $banks->get($section['question_bank_id'])), $data['sections']);

        foreach (app(ExamSectionDraw::class)->draw($sections) as [$section, $questions]) {
            $bank = $section->questionBank;
            if ($bank === null) {
                return array_replace($paper, ['questions' => [], 'error' => 'یکی از بانک‌های انتخاب‌شده در دسترس نیست.']);
            }

            if ($section->question_ids !== null && (count($section->question_ids) !== $section->question_count || $questions->count() !== $section->question_count)) {
                return array_replace($paper, ['questions' => [], 'error' => "سؤال‌های انتخاب‌شدهٔ بانک «{$bank->title}» را بررسی کنید."]);
            }

            if ($questions->count() < $section->question_count) {
                return array_replace($paper, ['questions' => [], 'error' => "بانک «{$bank->title}» به تعداد درخواستی سؤال فعال و غیرتکراری ندارد."]);
            }

            foreach ($questions as $question) {
                $paper['questions'][] = $this->question(
                    $question->body, $question->options ?? [], $question->type, empty($data['is_practice']) ? $question->score : null,
                );
            }
        }

        return $paper;
    }

    private function paper(string $title, ?string $classTitle, ?CarbonInterface $startsAt): array
    {
        return [
            'title' => $title,
            'branch' => Tenant::current()?->name,
            'class' => $classTitle,
            'date' => $startsAt === null ? null : CalendarUtils::strftime('l Y/m/d', $startsAt->toMutable()),
            'time' => $startsAt?->format('H:i'),
            'description' => '',
            'duration' => null,
            'questions' => [],
            'error' => null,
        ];
    }

    private function question(string $body, array $options, ?QuestionType $type, ?float $score): array
    {
        return [
            'body' => $body,
            'options' => array_values(array_column($options, 'text')),
            'written' => $type === QuestionType::Descriptive,
            'score' => $score,
        ];
    }
}
