<?php

namespace Modules\School\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\School\Enums\DescriptiveLevel;
use Modules\School\Enums\ScoreScale;
use Modules\School\Models\ClassroomGrade;
use Modules\School\Models\Curriculum;
use Modules\School\Models\Score;
use Modules\School\Models\ScorePeriod;

/**
 * The teachers' score book (ADR 0032). In a class with a grade, for a
 * score period of the class's academic year and a subject of the grade's
 * curriculum, each learner of the class gets a number out of 20 or a
 * descriptive level (by the stage's scale) and a note. The subject's
 * teacher in the class (a schedule's teacher) enters them until the
 * period's entry closes or it is published; whoever may edit the class, at
 * any time.
 */
class ScoreBook
{
    public const MAX_SCORE = 20;

    /**
     * The class's subjects: its grade's curriculum, for its major or all.
     *
     * @return Collection<int, Curriculum>
     */
    public function subjectsOf(Classroom $classroom): Collection
    {
        $grade = $this->gradeOf($classroom);

        if ($grade === null) {
            return collect();
        }

        return Curriculum::query()->with('subject')
            ->where('grade_id', $grade->grade_id)
            ->where(fn (Builder $query) => $query->whereNull('major_id')->when($grade->major_id, fn (Builder $query, int $major) => $query->orWhere('major_id', $major)))
            ->orderBy('sort')
            ->get();
    }

    public function scaleOf(Classroom $classroom): ScoreScale
    {
        return $this->gradeOf($classroom)?->grade?->stage?->scale ?? ScoreScale::Numeric;
    }

    /**
     * The class's current attendees, by name.
     *
     * @return Collection<int, User>
     */
    public function learners(Classroom $classroom): Collection
    {
        $ids = ClassMember::withoutTenantScope()->where('classroom_id', $classroom->getKey())->current()->attendees()->pluck('user_id');

        return User::query()->whereKey($ids)->orderBy('name')->get();
    }

    /**
     * Score periods of the class's academic year.
     *
     * @return Collection<int, ScorePeriod>
     */
    public function periodsOf(Classroom $classroom): Collection
    {
        return $classroom->term_id === null ? collect() : ScorePeriod::definedPeriods()->where('term_id', $classroom->term_id)->get();
    }

    public function teaches(User $user, Classroom $classroom, int $subjectId): bool
    {
        return ClassSchedule::withoutTenantScope()
            ->where('classroom_id', $classroom->getKey())
            ->where('subject_id', $subjectId)
            ->where('teacher_id', $user->getKey())
            ->exists();
    }

    /**
     * Why the user can't enter the subject's scores for the period in the
     * class now, or null.
     */
    public function refusal(User $user, Classroom $classroom, ScorePeriod $period, int $subjectId): ?string
    {
        if ($this->gradeOf($classroom) === null || $period->term_id !== $classroom->term_id) {
            return 'این دوره‌ی نمره‌دهی برای سال تحصیلی این کلاس نیست.';
        }

        if (! $this->subjectsOf($classroom)->contains('subject_id', $subjectId)) {
            return 'این درس در برنامه‌ی درسی پایه‌ی این کلاس نیست.';
        }

        if ($user->can('update', $classroom)) {
            return null;
        }

        if (! $this->teaches($user, $classroom, $subjectId)) {
            return 'شما مدرس این درس در این کلاس نیستید.';
        }

        if (! $period->isOpenForTeachers()) {
            return 'ثبت نمره‌ی این دوره بسته است.';
        }

        return null;
    }

    /**
     * The subjects the user teaches in classes with a grade in the current
     * tenant: what their score book lists.
     *
     * @return Collection<int, array{classroom: Classroom, subject: Curriculum}>
     */
    public function assignmentsOf(User $user): Collection
    {
        return ClassSchedule::query()->where('teacher_id', $user->getKey())->whereNotNull('subject_id')
            ->with('classroom')
            ->get()
            ->unique(fn (ClassSchedule $schedule): string => $schedule->classroom_id.'-'.$schedule->subject_id)
            ->flatMap(function (ClassSchedule $schedule): array {
                $curriculum = $schedule->classroom ? $this->subjectsOf($schedule->classroom)->firstWhere('subject_id', $schedule->subject_id) : null;

                return $curriculum === null ? [] : [['classroom' => $schedule->classroom, 'subject' => $curriculum]];
            })
            ->sortBy(fn (array $assignment): string => $assignment['classroom']->title.' '.$assignment['subject']->subject->title)
            ->values();
    }

    /**
     * @return Collection<int, Score> by user id
     */
    public function scores(Classroom $classroom, ScorePeriod $period, int $subjectId): Collection
    {
        return Score::withoutTenantScope()->where('tenant_id', $classroom->tenant_id)->where('period_id', $period->getKey())
            ->where('subject_id', $subjectId)->get()->keyBy('user_id');
    }

    /**
     * Saves a subject's scores; a row with no score, level or note removes it.
     *
     * @param  array<int, array{score?: int|float|string|null, level?: DescriptiveLevel|string|null, note?: string|null}>  $rows  user id => row
     *
     * @throws SchoolException
     */
    public function record(Classroom $classroom, ScorePeriod $period, int $subjectId, array $rows, User $by): void
    {
        if (($refusal = $this->refusal($by, $classroom, $period, $subjectId)) !== null) {
            throw new SchoolException($refusal);
        }

        if (array_diff(array_keys($rows), $this->learners($classroom)->modelKeys()) !== []) {
            throw new SchoolException('فقط '.config('sarasystem.learner_plural_label').'ی این کلاس نمره می‌گیرند.');
        }

        $numeric = $this->scaleOf($classroom) === ScoreScale::Numeric;

        DB::transaction(function () use ($classroom, $period, $subjectId, $rows, $by, $numeric): void {
            foreach ($rows as $userId => $row) {
                $score = $numeric && filled($row['score'] ?? null) ? (float) $row['score'] : null;
                $level = ! $numeric && filled($row['level'] ?? null)
                    ? ($row['level'] instanceof DescriptiveLevel ? $row['level'] : DescriptiveLevel::from($row['level']))
                    : null;
                $note = filled($row['note'] ?? null) ? trim($row['note']) : null;

                if ($score !== null && ($score < 0 || $score > self::MAX_SCORE)) {
                    throw new SchoolException('نمره باید بین ۰ و '.self::MAX_SCORE.' باشد.');
                }

                $key = ['tenant_id' => $classroom->tenant_id, 'period_id' => $period->getKey(), 'user_id' => $userId, 'subject_id' => $subjectId];

                if ($score === null && $level === null && $note === null) {
                    Score::withoutTenantScope()->where($key)->delete();

                    continue;
                }

                $existing = Score::withoutTenantScope()->where($key)->first();
                if ($existing !== null && $existing->level === $level && $existing->note === $note
                    && ($existing->score === null ? $score === null : (float) $existing->score === $score)) {
                    continue;
                }

                Score::withoutTenantScope()->updateOrCreate($key, [
                    'classroom_id' => $classroom->getKey(),
                    'score' => $score,
                    'level' => $level,
                    'note' => $note,
                    'entered_by_id' => $by->getKey(),
                    'entered_at' => now(),
                ]);
            }
        });
    }

    private function gradeOf(Classroom $classroom): ?ClassroomGrade
    {
        return ClassroomGrade::withoutTenantScope()->with('grade.stage')->where('classroom_id', $classroom->getKey())->first();
    }
}
