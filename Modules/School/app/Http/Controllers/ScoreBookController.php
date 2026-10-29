<?php

namespace Modules\School\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Class\Models\Classroom;
use Modules\School\Enums\DescriptiveLevel;
use Modules\School\Models\Curriculum;
use Modules\School\Models\ScorePeriod;
use Modules\School\Services\SchoolException;
use Modules\School\Services\ScoreBook;

/**
 * «دفتر نمره» in the user panel (ADR 0032): the subjects a teacher teaches
 * in classes with a grade, and for each, the period's score per learner.
 */
class ScoreBookController extends Controller
{
    public function index(Request $request, ScoreBook $book): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('School::ScoreBooks', [
            'assignments' => $book->assignmentsOf($user)->map(fn (array $assignment): array => [
                'classroom_id' => $assignment['classroom']->getKey(),
                'classroom' => $assignment['classroom']->title,
                'subject_id' => $assignment['subject']->subject_id,
                'subject' => $assignment['subject']->subject->title,
                'periods' => $book->periodsOf($assignment['classroom'])->map(fn (ScorePeriod $period): array => [
                    'id' => $period->getKey(), 'title' => $period->title, 'is_open' => $period->isOpenForTeachers(),
                ])->values(),
            ]),
        ]);
    }

    public function show(Request $request, Classroom $classroom, int $subjectId, ScoreBook $book): Response
    {
        /** @var User $user */
        $user = $request->user();
        $curriculum = $this->curriculum($book, $classroom, $subjectId);
        $periods = $book->periodsOf($classroom);
        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first(fn (ScorePeriod $period): bool => $period->isOpenForTeachers()) ?? $periods->last();
        abort_unless($user->can('update', $classroom) || $book->teaches($user, $classroom, $subjectId), 404);

        $scores = $period ? $book->scores($classroom, $period, $subjectId) : collect();

        return Inertia::render('School::ScoreBook', [
            'classroom' => ['id' => $classroom->getKey(), 'title' => $classroom->title],
            'subject' => ['id' => $subjectId, 'title' => $curriculum->subject->title],
            'scale' => $book->scaleOf($classroom)->value,
            'maxScore' => ScoreBook::MAX_SCORE,
            'periods' => $periods->map(fn (ScorePeriod $item): array => ['id' => $item->getKey(), 'title' => $item->title, 'is_open' => $item->isOpenForTeachers()])->values(),
            'period' => $period ? ['id' => $period->getKey(), 'title' => $period->title] : null,
            'refusal' => $period ? $book->refusal($user, $classroom, $period, $subjectId) : 'دوره‌ی نمره‌دهی‌ای برای سال تحصیلی این کلاس تعریف نشده است.',
            'levels' => collect(DescriptiveLevel::cases())->map(fn (DescriptiveLevel $level): array => ['value' => $level->value, 'label' => $level->getLabel()]),
            'rows' => $book->learners($classroom)->map(fn (User $learner): array => [
                'user_id' => $learner->getKey(),
                'name' => $learner->name,
                'score' => $scores->get($learner->getKey())?->score,
                'level' => $scores->get($learner->getKey())?->level?->value ?? '',
                'note' => $scores->get($learner->getKey())?->note ?? '',
            ])->values(),
        ]);
    }

    public function store(Request $request, Classroom $classroom, int $subjectId, ScoreBook $book): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->curriculum($book, $classroom, $subjectId);

        $data = $request->validate([
            'period_id' => ['required', 'integer'],
            'rows' => ['required', 'array'],
            'rows.*.user_id' => ['required', 'integer'],
            'rows.*.score' => ['nullable', 'numeric', 'min:0', 'max:'.ScoreBook::MAX_SCORE],
            'rows.*.level' => ['nullable', Rule::enum(DescriptiveLevel::class)],
            'rows.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $period = ScorePeriod::definedPeriods()->findOrFail($data['period_id']);

        try {
            $book->record($classroom, $period, $subjectId, collect($data['rows'])->keyBy(fn (array $row): int => (int) $row['user_id'])->all(), $user);
        } catch (SchoolException $exception) {
            throw ValidationException::withMessages(['form' => $exception->getMessage()]);
        }

        return back()->with('status', 'نمره‌ها ذخیره شد.');
    }

    private function curriculum(ScoreBook $book, Classroom $classroom, int $subjectId): Curriculum
    {
        return $book->subjectsOf($classroom)->firstWhere('subject_id', $subjectId) ?? abort(404);
    }
}
