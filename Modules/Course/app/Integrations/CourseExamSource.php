<?php

namespace Modules\Course\Integrations;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Support\CoursePermissions;
use Modules\Exam\Interfaces\SharedExamContextSource;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamSection;
use Modules\Exam\Support\ExamContext;
use Modules\Exam\Support\ExamContexts;

class CourseExamSource implements SharedExamContextSource
{
    public const string TYPE = 'course-lesson';

    public const string INVALID_CODE = 'آزمون مستقلی با این کد در دسترس نیست.';

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
                : CourseResource::getUrl('lessons', ['record' => $lesson->course], tenant: Tenant::primary() ?? $tenant),
            $canManage, $canParticipate,
            hostLabel: 'درس',
            allowsDirectCreation: false,
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

    public static function sourceExam(string $code): ?Exam
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return null;
        }

        $tenantIds = array_filter([
            $actor->can('View:Exam') ? Tenant::current()?->getKey() : null,
            CoursePermissions::allows($actor, 'View:Exam') ? Tenant::primary()?->getKey() : null,
        ]);

        return Exam::withoutTenantScope()->whereIn('tenant_id', $tenantIds)
            ->whereNull('context_type')->where('code', $code)->first();
    }

    public static function linkedCode(CourseLesson $lesson): ?string
    {
        $exam = self::lessonExams($lesson->getKey())->oldest('id')->first(['code', 'source_exam_code']);

        return $exam?->source_exam_code ?? $exam?->code;
    }

    public static function attachByCode(CourseLesson $lesson, string $code, string $errorField): void
    {
        Gate::authorize('update', $lesson->course);

        // The form transaction covers the lesson and the copied exam. The row lock
        // serializes attachments from concurrent edits of the same lesson.
        CourseLesson::query()->whereKey($lesson->getKey())->lockForUpdate()->firstOrFail();

        if (($linkedCode = self::linkedCode($lesson)) !== null) {
            if ($linkedCode !== $code) {
                throw ValidationException::withMessages([$errorField => 'برای این درس قبلاً آزمون ثبت شده است.']);
            }

            return;
        }

        $source = self::sourceExam($code);
        if ($source === null) {
            throw ValidationException::withMessages([$errorField => self::INVALID_CODE]);
        }

        $exam = $source->replicate(['code', 'source_exam_code', 'user_panel_locked']);
        $exam->forceFill([
            'context_type' => self::TYPE,
            'context_id' => $lesson->getKey(),
            'assigned_by_id' => auth()->id(),
            'source_exam_code' => $code,
        ])->save();

        $exam->sections()->createMany($source->sections()->get()->map(
            fn (ExamSection $section): array => $section->only('question_bank_id', 'question_count', 'question_ids', 'sort'),
        ));
    }

    private static function lessonExams(int $lesson): Builder
    {
        return Exam::withoutTenantScope()->where('context_type', self::TYPE)->where('context_id', $lesson);
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
        $existing = self::lessonExams($lesson)->oldest('id')->value('tenant_id');

        return $existing === null ? Tenant::primary() : Tenant::find($existing);
    }

    public static function managementUrl(CourseLesson $lesson): string
    {
        return app(ExamContexts::class)->url(self::TYPE, $lesson->getKey());
    }
}
