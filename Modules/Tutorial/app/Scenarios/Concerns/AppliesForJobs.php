<?php

namespace Modules\Tutorial\Scenarios\Concerns;

use App\Workflow\WorkflowEngine;
use Closure;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Enums\EducationLevel;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Enums\Nationality;
use Modules\Employee\Enums\UniversityType;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;

/**
 * The «همکاری با ما» form as `perform()` sends it (ADR 0040): an
 * application with every field of the default form, then the position's
 * workflow starts, as CareersController does.
 */
trait AppliesForJobs
{
    /**
     * @param  array{first_name: string, last_name: string, national_code: string, gender: Gender, mobile: string}  $person
     * @param  (Closure(JobApplication): void)|null  $beforeStart  e.g. uploads its documents
     */
    protected static function applyForJob(JobPosition $position, array $person, ?Closure $beforeStart = null): JobApplication
    {
        return $position->tenant->execute(function () use ($position, $person, $beforeStart): JobApplication {
            $application = JobApplication::query()->create([
                ...$person,
                'tenant_id' => $position->tenant_id,
                'job_position_id' => $position->getKey(),
                'job_title' => $position->title,
                'status' => ApplicationStatus::Submitted,
                'father_name' => 'محمد',
                'nationality' => Nationality::Iranian,
                'birth_date' => '1995-03-21',
                'postal_code' => '1969764311',
                'address' => 'تهران',
                'education' => [[
                    'level' => EducationLevel::Bachelor->value, 'major' => 'مدیریت', 'orientation' => null,
                    'average' => 17.5, 'rank' => null, 'graduation_date' => '2018-06-21',
                    'university_type' => UniversityType::State->value, 'university' => 'دانشگاه تهران',
                ]],
                'work_history' => [],
                'custom_fields' => [],
            ]);

            if ($beforeStart !== null) {
                $beforeStart($application);
            }

            app(WorkflowEngine::class)->start($position->workflow, $application);

            return $application;
        });
    }
}
