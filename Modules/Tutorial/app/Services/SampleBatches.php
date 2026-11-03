<?php

namespace Modules\Tutorial\Services;

use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\HomeworkSubmission;
use Modules\Class\Models\PlanTopic;
use Modules\Class\Models\Subject;
use Modules\Class\Models\TeachingPlan;
use Modules\Class\Models\Term;
use Modules\Tutorial\Models\SampleBatch;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sample-data batches (ADR 0046): what one made, and deleting it.
 */
class SampleBatches
{
    /**
     * Persian names of the rows a batch makes, for its summary.
     *
     * @return array<string, string> model class => label
     */
    private static function labels(): array
    {
        return [
            User::class => 'حساب کاربری',
            Guardianship::class => 'ولی',
            Term::class => 'دوره‌ی آموزشی',
            Classroom::class => 'کلاس و زیرکلاس',
            ClassMember::class => 'عضویت در کلاس',
            Subject::class => 'درس',
            TeachingPlan::class => 'طرح درس',
            PlanTopic::class => 'سرفصل',
            ClassSchedule::class => 'برنامه‌ی هفتگی',
            ClassSession::class => 'جلسه',
            Attendance::class => 'حضور و غیاب',
            HomeworkSubmission::class => 'تکلیف تحویل‌شده',
            'Modules\\Student\\Models\\Student' => config('sarasystem.learner_label'),
            'Modules\\Employee\\Models\\Employee' => 'کارمند',
            'Modules\\School\\Models\\Enrollment' => 'پرونده‌ی تحصیلی',
            'Modules\\School\\Models\\ClassroomGrade' => 'پایه‌ی کلاس',
        ];
    }

    public function __construct(private RecordedRowsEraser $eraser) {}

    /**
     * @return array<string, int> label => rows
     */
    public function summary(SampleBatch $batch): array
    {
        return $batch->records()
            ->selectRaw('model_type, count(*) as rows')
            ->groupBy('model_type')
            ->orderByRaw('min(id)')
            ->pluck('rows', 'model_type')
            ->mapWithKeys(fn (int $rows, string $type): array => [self::labels()[$type] ?? class_basename($type) => $rows])
            ->all();
    }

    /**
     * Deletes every row the batch made, then the batch. All or nothing.
     *
     * @return int the rows deleted
     *
     * @throws RuntimeException when a row made since (outside the batch) still uses one of them
     */
    public function delete(SampleBatch $batch): int
    {
        $count = DB::transaction(function () use ($batch): int {
            $count = $this->eraser->erase($batch->records()->orderByDesc('id')->get()->all());
            $batch->delete();

            return $count;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $count;
    }

    /**
     * The batch's (top-level) class, if it is still there.
     */
    public function classroom(SampleBatch $batch): ?Classroom
    {
        return $batch->made(Classroom::class)->whereNull('parent_id')->first();
    }

    /**
     * Whether a tenant is still there for the batch's links.
     */
    public function tenant(SampleBatch $batch): ?Tenant
    {
        return $batch->tenant_id ? Tenant::query()->find($batch->tenant_id) : null;
    }
}
