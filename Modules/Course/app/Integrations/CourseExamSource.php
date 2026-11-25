<?php

namespace Modules\Course\Integrations;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Models\CourseLesson;
use Modules\Exam\Interfaces\SharedExamContextSource;
use Modules\Exam\Models\Exam;
use Modules\Exam\Support\ExamContext;
use Modules\Exam\Support\ExamContexts;

class CourseExamSource implements SharedExamContextSource
{
    public const string TYPE = 'course-lesson';

    public function tenantForVisit(int $id, User $user): ?Tenant
    {
        $lesson = CourseLesson::query()->with('course')->find($id);
        if ($lesson === null || ! ($this->participantIds($user)->whereKey($id)->exists()
            || Gate::forUser($user)->allows('update', $lesson->course))) {
            return null;
        }

        return $this->storageTenant($id);
    }

    public function resolve(int $id, User $user): ?ExamContext
    {
        $lesson = CourseLesson::query()->with('course')->find($id);

        $tenant = $this->storageTenant($id);
        if ($lesson?->course === null || $tenant === null || ! $tenant->isCurrent()) {
            return null;
        }

        $canManage = Gate::forUser($user)->allows('update', $lesson->course);
        $canParticipate = $this->participantIds($user)->whereKey($id)->exists();

        if (! $canManage && ! $canParticipate) {
            return null;
        }

        return new ExamContext(
            self::TYPE, $id, $tenant->getKey(),
            $lesson->course->title.' · '.$lesson->title,
            null, null,
            $canParticipate
                ? route('courses.lesson', [$lesson->course, $lesson])
                : CourseResource::getUrl('edit', ['record' => $lesson->course], tenant: Tenant::primary() ?? $tenant),
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
        $id = $lesson->getKey();
        $tenant = $this->storageTenant($id);

        return $tenant !== null && Exam::withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())->where('context_type', self::TYPE)->where('context_id', $id)
            ->published()->forParticipant($user)->exists()
            ? app(ExamContexts::class)->url(self::TYPE, $id)
            : null;
    }

    private function storageTenant(int $lesson): ?Tenant
    {
        $existing = Exam::withoutTenantScope()->where('context_type', self::TYPE)
            ->where('context_id', $lesson)->oldest('id')->value('tenant_id');

        return $existing === null ? Tenant::primary() : Tenant::find($existing);
    }

    public static function managementUrl(CourseLesson $lesson): string
    {
        return app(ExamContexts::class)->url(self::TYPE, $lesson->getKey());
    }
}
