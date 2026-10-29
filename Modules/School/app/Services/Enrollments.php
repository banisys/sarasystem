<?php

namespace Modules\School\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Term;
use Modules\School\Models\ClassroomGrade;
use Modules\School\Models\Enrollment;
use Modules\School\Models\Grade;
use Modules\School\Models\Major;

/**
 * Classes' grades and learners' academic records (ADR 0031). A class may
 * have a grade (and a major for grades that have them). A learner who
 * joins a top-level class with a grade and a term as an attendee gets that
 * grade for the term; promotion makes next year's record one grade up.
 */
class Enrollments
{
    /**
     * Sets (or, with null, clears) the class's grade; the records of its
     * learners follow.
     *
     * @throws SchoolException
     */
    public function setClassroomGrade(Classroom $classroom, ?int $gradeId, ?int $majorId): void
    {
        if ($gradeId === null) {
            ClassroomGrade::withoutTenantScope()->where('classroom_id', $classroom->getKey())->delete();

            return;
        }

        [$grade, $majorId] = $this->checkGrade($gradeId, $majorId);

        DB::transaction(function () use ($classroom, $grade, $majorId): void {
            ClassroomGrade::withoutTenantScope()->updateOrCreate(
                ['classroom_id' => $classroom->getKey()],
                ['tenant_id' => $classroom->tenant_id, 'grade_id' => $grade->getKey(), 'major_id' => $majorId],
            );

            Enrollment::withoutTenantScope()->where('classroom_id', $classroom->getKey())
                ->update(['grade_id' => $grade->getKey(), 'major_id' => $majorId]);

            ClassMember::withoutTenantScope()->where('classroom_id', $classroom->getKey())->current()->attendees()->get()
                ->each(fn (ClassMember $member) => $this->memberJoined($member));
        });
    }

    /**
     * A learner joined a class: a top-level class with a grade and a term
     * becomes their record for that term.
     */
    public function memberJoined(ClassMember $member): void
    {
        $classroom = Classroom::withoutTenantScope()->find($member->classroom_id);
        $classroomGrade = ClassroomGrade::withoutTenantScope()->where('classroom_id', $member->classroom_id)->first();

        if ($classroom === null || $classroomGrade === null || $classroom->parent_id !== null || $classroom->term_id === null
            || ! $member->classRole()->value('is_attendee')) {
            return;
        }

        Enrollment::withoutTenantScope()->updateOrCreate(
            ['tenant_id' => $classroom->tenant_id, 'user_id' => $member->user_id, 'term_id' => $classroom->term_id],
            ['grade_id' => $classroomGrade->grade_id, 'major_id' => $classroomGrade->major_id, 'classroom_id' => $classroom->getKey()],
        );
    }

    /**
     * @throws SchoolException
     */
    public function save(?Enrollment $enrollment, User $learner, int $termId, int $gradeId, ?int $majorId, ?int $classroomId, int $tenantId): Enrollment
    {
        [$grade, $majorId] = $this->checkGrade($gradeId, $majorId);

        if (! Term::definedTerms()->whereKey($termId)->exists()) {
            throw new SchoolException('سال تحصیلی پیدا نشد.');
        }

        $duplicate = Enrollment::withoutTenantScope()->where('tenant_id', $tenantId)->where('user_id', $learner->getKey())->where('term_id', $termId)
            ->when($enrollment, fn ($query) => $query->whereKeyNot($enrollment->getKey()))->exists();
        if ($duplicate) {
            throw new SchoolException("«{$learner->name}» برای این سال تحصیلی پرونده دارد.");
        }

        $enrollment ??= new Enrollment(['tenant_id' => $tenantId, 'user_id' => $learner->getKey()]);
        $enrollment->fill(['term_id' => $termId, 'grade_id' => $grade->getKey(), 'major_id' => $majorId, 'classroom_id' => $classroomId])->save();

        return $enrollment;
    }

    /**
     * Next year's records, one grade up, in the same major when the next
     * grade has majors; the class is chosen later. Learners in the last
     * grade, and those who have a record for that year, are left out.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     * @return array{promoted: int, graduated: int, skipped: int}
     */
    public function promote(Collection $enrollments, Term $to): array
    {
        $result = ['promoted' => 0, 'graduated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($enrollments, $to, &$result): void {
            foreach ($enrollments as $enrollment) {
                $next = $enrollment->grade?->next();

                if ($next === null) {
                    $result['graduated']++;

                    continue;
                }

                $exists = Enrollment::withoutTenantScope()->where('tenant_id', $enrollment->tenant_id)
                    ->where('user_id', $enrollment->user_id)->where('term_id', $to->getKey())->exists();

                if ($exists || $enrollment->term_id === $to->getKey()) {
                    $result['skipped']++;

                    continue;
                }

                Enrollment::withoutTenantScope()->create([
                    'tenant_id' => $enrollment->tenant_id,
                    'user_id' => $enrollment->user_id,
                    'term_id' => $to->getKey(),
                    'grade_id' => $next->getKey(),
                    'major_id' => $next->has_majors ? $enrollment->major_id : null,
                ]);
                $result['promoted']++;
            }
        });

        return $result;
    }

    /**
     * The grade and, only for grades that have majors, a major.
     *
     * @return array{Grade, ?int}
     *
     * @throws SchoolException
     */
    private function checkGrade(int $gradeId, ?int $majorId): array
    {
        $grade = Grade::definedGrades()->find($gradeId) ?? throw new SchoolException('پایه پیدا نشد.');

        if (! $grade->has_majors) {
            return [$grade, null];
        }

        if ($majorId === null || ! Major::definedMajors()->whereKey($majorId)->exists()) {
            throw new SchoolException("پایه‌ی «{$grade->title}» رشته دارد؛ رشته را انتخاب کنید.");
        }

        return [$grade, $majorId];
    }
}
