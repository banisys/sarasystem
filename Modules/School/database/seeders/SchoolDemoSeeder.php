<?php

namespace Modules\School\Database\Seeders;

use App\Models\Tenant;
use Database\Seeders\Concerns\GrantsDemoPermissions;
use Illuminate\Database\Seeder;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Subject;
use Modules\Class\Models\Term;
use Modules\School\Enums\ScoreScale;
use Modules\School\Models\Curriculum;
use Modules\School\Models\Grade;
use Modules\School\Models\Major;
use Modules\School\Models\ScorePeriod;
use Modules\School\Models\Stage;
use Modules\School\Services\Enrollments;
use RuntimeException;

/**
 * An Iranian school's structure for local development (ADR 0031), on top
 * of the Class demo data: the three stages with their grades, the upper
 * secondary majors, grade 7's curriculum (the demo subjects), and each
 * branch's «هفتم الف» set to grade 7, which fills in its learners' records,
 * and the first score periods of the demo year (ADR 0032).
 * The demo roles get the school permissions. Safe to run again.
 */
class SchoolDemoSeeder extends Seeder
{
    use GrantsDemoPermissions;

    /** @var array<string, array{scale: ScoreScale, majors: bool, grades: list<string>}> */
    private const array STAGES = [
        'دبستان' => ['scale' => ScoreScale::Descriptive, 'majors' => false, 'grades' => ['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم']],
        'متوسطه اول' => ['scale' => ScoreScale::Numeric, 'majors' => false, 'grades' => ['هفتم', 'هشتم', 'نهم']],
        'متوسطه دوم' => ['scale' => ScoreScale::Numeric, 'majors' => true, 'grades' => ['دهم', 'یازدهم', 'دوازدهم']],
    ];

    /** Grade 7's subjects: demo subject => weekly hours. */
    private const array GRADE_SEVEN = ['ریاضی' => 4, 'علوم تجربی' => 4, 'ادبیات فارسی' => 5, 'زبان انگلیسی' => 2];

    public function run(): void
    {
        $headOffice = Tenant::primary() ?? throw new RuntimeException('No primary tenant: run the core seeder first.');

        $this->grantDemoPermissions([
            'hq_member' => ['ViewAny:Stage', 'View:Stage', 'Create:Stage', 'Update:Stage', 'Delete:Stage', 'Reorder:Stage',
                'ViewAny:Grade', 'View:Grade', 'Create:Grade', 'Update:Grade', 'Delete:Grade', 'Reorder:Grade',
                'ViewAny:Major', 'View:Major', 'Create:Major', 'Update:Major', 'Delete:Major', 'Reorder:Major',
                'ViewAny:Enrollment', 'View:Enrollment',
                'ViewAny:ScorePeriod', 'View:ScorePeriod', 'Create:ScorePeriod', 'Update:ScorePeriod', 'Delete:ScorePeriod', 'Reorder:ScorePeriod'],
            'principal' => ['ViewAny:Enrollment', 'View:Enrollment', 'Create:Enrollment', 'Update:Enrollment', 'Delete:Enrollment'],
        ]);

        $seventh = $headOffice->execute(function (): Grade {
            $stageSort = 0;
            foreach (self::STAGES as $title => $stage) {
                $model = Stage::query()->updateOrCreate(['title' => $title], ['scale' => $stage['scale'], 'sort' => ++$stageSort]);
                foreach ($stage['grades'] as $sort => $grade) {
                    Grade::query()->updateOrCreate(['title' => $grade], ['stage_id' => $model->getKey(), 'has_majors' => $stage['majors'], 'sort' => $sort + 1]);
                }
            }

            foreach (['ریاضی و فیزیک', 'علوم تجربی', 'ادبیات و علوم انسانی'] as $sort => $major) {
                Major::query()->updateOrCreate(['title' => $major], ['sort' => $sort + 1]);
            }

            $seventh = Grade::query()->where('title', 'هفتم')->sole();
            $sort = 0;
            foreach (self::GRADE_SEVEN as $subject => $hours) {
                $subjectId = Subject::query()->where('title', $subject)->value('id');
                if ($subjectId !== null) {
                    Curriculum::query()->updateOrCreate(
                        ['grade_id' => $seventh->getKey(), 'major_id' => null, 'subject_id' => $subjectId],
                        ['coefficient' => 1, 'weekly_hours' => $hours, 'sort' => ++$sort],
                    );
                }
            }

            // The demo year's first score periods (ADR 0032): the months of the first half and «نوبت اول».
            $term = Term::query()->where('title', 'سال تحصیلی ۱۴۰۵-۱۴۰۶')->first();
            if ($term !== null) {
                foreach ([
                    ['مهر', '2026-09-23', '2026-10-22', '2026-10-29'], ['آبان', '2026-10-23', '2026-11-21', '2026-11-28'],
                    ['آذر', '2026-11-22', '2026-12-21', '2026-12-28'], ['نوبت اول', '2026-09-23', '2027-01-20', '2027-01-27'],
                ] as $sort => [$title, $from, $to, $closes]) {
                    ScorePeriod::query()->updateOrCreate(
                        ['term_id' => $term->getKey(), 'title' => $title],
                        ['starts_on' => $from, 'ends_on' => $to, 'entry_closes_on' => $closes, 'sort' => $sort + 1],
                    );
                }
            }

            return $seventh;
        });

        Tenant::query()->whereKeyNot($headOffice->getKey())->where('is_active', true)->get()->each(fn (Tenant $branch) => $branch->execute(function () use ($seventh): void {
            Classroom::query()->where('title', 'هفتم الف')->get()
                ->each(fn (Classroom $classroom) => app(Enrollments::class)->setClassroomGrade($classroom, $seventh->getKey(), null));
        }));
    }
}
