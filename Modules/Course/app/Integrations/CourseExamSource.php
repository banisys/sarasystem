<?php

namespace Modules\Course\Integrations;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Models\CourseLesson;
use Modules\Exam\Interfaces\ExamContextSource;
use Modules\Exam\Services\ContextExams;
use Modules\Exam\Support\ExamContext;
use Modules\Exam\Support\ExamContexts;

class CourseExamSource implements ExamContextSource
{
    public const string TYPE = 'course-lesson';

    public function tenantForVisit(int $id, User $user): ?Tenant
    {
        $tenants = $user->isSuperAdmin() ? Tenant::query() : $user->tenants();

        return $tenants->active()
            ->whereIn('tenants.id', CourseLesson::withoutTenantScope()->whereKey($id)->select('tenant_id'))
            ->first();
    }

    public function resolve(int $id, User $user): ?ExamContext
    {
        $lesson = CourseLesson::query()->with('course')->find($id);

        if ($lesson?->course === null) {
            return null;
        }

        $canManage = Gate::forUser($user)->allows('update', $lesson->course);
        $canParticipate = $this->participantIds($user)->whereKey($id)->exists();

        if (! $canManage && ! $canParticipate) {
            return null;
        }

        return new ExamContext(
            self::TYPE, $id, $lesson->tenant_id,
            $lesson->course->title.' · '.$lesson->title,
            null, null,
            $canParticipate
                ? route('courses.lesson', [$lesson->course, $lesson])
                : CourseResource::getUrl('edit', ['record' => $lesson->course], tenant: Tenant::current()),
            $canManage, $canParticipate,
            hostLabel: 'درس',
        );
    }

    public function participantIds(User $user): Builder
    {
        return CourseLesson::query()->select('course_lessons.id')
            ->whereHas('course', fn (Builder $course): Builder => $course->published()
                ->whereHas('enrollments', fn (Builder $enrollments): Builder => $enrollments->active()->where('user_id', $user->getKey())));
    }

    public function hasTeachingStaff(User $user): bool
    {
        return false;
    }

    public function subjectsFor(array $contextIds): array
    {
        return CourseLesson::query()->whereKey($contextIds)->get(['id', 'title'])
            ->mapWithKeys(fn (CourseLesson $lesson): array => [$lesson->id => $lesson->only('id', 'title')])
            ->all();
    }

    public function publishedUrl(CourseLesson $lesson, User $user): ?string
    {
        $context = $this->resolve($lesson->getKey(), $user);

        return $context !== null && $context->canParticipate && app(ContextExams::class)->query($context)->published()->exists()
            ? app(ExamContexts::class)->url(self::TYPE, $lesson->getKey())
            : null;
    }

    public static function managementUrl(CourseLesson $lesson): string
    {
        return app(ExamContexts::class)->url(self::TYPE, $lesson->getKey());
    }
}
