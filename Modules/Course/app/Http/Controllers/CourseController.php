<?php

namespace Modules\Course\Http\Controllers;

use App\Authorization\DefaultRoles;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Integrations\CourseExamSource;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseEnrollmentSettings;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;
use Modules\Course\Services\LessonContent;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canShowAll = $this->canBrowseCatalog($user);
        $showAll = $canShowAll && $request->boolean('all');
        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, 100) : '';
        $myActiveEnrollment = fn (Builder $query): Builder => $query->active()->where('user_id', $user->getKey());

        $courses = Course::query()->published()
            ->when(! $showAll, fn (Builder $query): Builder => $query->whereHas('enrollments', $myActiveEnrollment))
            ->when($search !== '', fn (Builder $query): Builder => $query->whereLike('title', '%'.$search.'%'))
            ->withExists(['enrollments as enrolled' => $myActiveEnrollment])
            ->with('category:id,title')
            ->withCount('lessons')
            ->latest('id')
            ->paginate(15)
            ->appends([
                'all' => $showAll ? 1 : null,
                'search' => $search !== '' ? $search : null,
            ])
            ->through(fn (Course $course): array => [
                ...$course->only('title', 'lessons_count', 'enrolled'),
                'category' => $course->category?->title,
                'cover_url' => filled($course->cover_image) ? route('courses.cover', $course) : null,
                'url' => route('courses.show', $course),
            ]);

        return Inertia::render('Course::Index', [
            'courses' => $courses,
            'canShowAll' => $canShowAll,
            'showAll' => $showAll,
            'search' => $search,
        ]);
    }

    public function cover(Request $request, Course $course): StreamedResponse
    {
        abort_unless($course->is_published, 404);
        $user = $request->user();
        abort_unless($this->canBrowseCatalog($user) || $this->isEnrolled($course, $user), 403);

        $disk = Storage::disk('local');
        $cover = $course->cover_image;
        abort_unless(filled($cover) && $disk->fileExists($cover), 404);

        $mimeType = $disk->mimeType($cover);
        abort_unless(in_array($mimeType, Course::IMAGE_TYPES, true), 404);

        return $disk->response($cover, headers: [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        abort_unless($course->is_published, 404);

        $user = $request->user();
        $enrollment = $course->enrollments()->where('user_id', $user->getKey())->first();
        $enrolled = $this->isEnrolled($course, $user);
        $showLessonCodes = $this->canBrowseCatalog($user);
        $course->load(['category:id,title', 'instructors.user:id,name', 'sections.lessons' => fn (HasMany $lessons) => $lessons
            ->select(['id', 'course_section_id', 'title', 'code', 'duration_minutes', 'is_preview'])
            ->withExists(['completions as completed' => fn (Builder $completed): Builder => $completed->where('user_id', $user->getKey())])]);

        return Inertia::render('Course::Show', [
            'course' => [
                ...$course->only('id', 'title', 'description'),
                'category' => $course->category?->title,
                'instructors' => $course->instructors->pluck('user.name')->all(),
            ],
            'sections' => $course->sections->map(fn (CourseSection $section): array => [
                ...$section->only('id', 'title'),
                'lessons' => $section->lessons->map(fn (CourseLesson $lesson): array => [
                    ...$lesson->only('id', 'title', 'duration_minutes', 'is_preview', 'completed'),
                    ...($showLessonCodes ? ['code' => $lesson->code] : []),
                    'url' => $enrolled || $lesson->is_preview ? route('courses.lesson', [$course, $lesson]) : null,
                ]),
            ]),
            'enrolled' => $enrolled,
            'canEnroll' => $course->allows_self_enrollment && $enrollment === null && CourseEnrollmentSettings::current()->free_enabled,
            'hasInactiveEnrollment' => $enrollment !== null && ! $enrolled,
        ]);
    }

    public function enroll(Request $request, Course $course): RedirectResponse
    {
        DB::transaction(function () use ($request, $course): void {
            $current = Course::query()->lockForUpdate()->findOrFail($course->getKey());
            abort_unless($current->is_published, 404);
            abort_unless($current->allows_self_enrollment && CourseEnrollmentSettings::current()->free_enabled, 403);

            $current->enrollments()->firstOrCreate(['user_id' => $request->user()->getKey()]);
            abort_unless($this->isEnrolled($current, $request->user()), 403);
        });

        return redirect()->route('courses.show', $course)->with('status', 'ثبت‌نام انجام شد.');
    }

    public function lesson(Request $request, Course $course, CourseLesson $lesson, LessonContent $content): Response
    {
        $enrolled = $this->authorizeLesson($course, $lesson, $request->user());
        $lesson->load('media');
        $neighbours = $course->sections()->with('lessons:id,course_section_id,is_preview')->get()
            ->flatMap->lessons->filter(fn (CourseLesson $item): bool => $enrolled || $item->is_preview)->values();
        $position = $neighbours->search(fn (CourseLesson $item): bool => $item->is($lesson));

        return Inertia::render('Course::Lesson', [
            'course' => $course->only('id', 'title'),
            ...$content->describe($lesson, fn (int $file): string => route('courses.lesson.file', [$course, $lesson, $file])),
            'completed' => $lesson->completions()->where('user_id', $request->user()->getKey())->exists(),
            'canComplete' => $enrolled,
            'examUrl' => $enrolled && Module::isEnabled('Exam')
                ? app(CourseExamSource::class)->publishedUrl($lesson, $request->user())
                : null,
            'previous' => isset($neighbours[$position - 1]) ? route('courses.lesson', [$course, $neighbours[$position - 1]]) : null,
            'next' => isset($neighbours[$position + 1]) ? route('courses.lesson', [$course, $neighbours[$position + 1]]) : null,
        ]);
    }

    public function complete(Request $request, Course $course, CourseLesson $lesson): RedirectResponse
    {
        abort_unless($this->authorizeLesson($course, $lesson, $request->user()), 403);

        $lesson->completions()->firstOrCreate(['user_id' => $request->user()->getKey()]);

        return redirect()->route('courses.lesson', [$course, $lesson])->with('status', 'پیشرفت شما ثبت شد.');
    }

    public function file(Request $request, Course $course, CourseLesson $lesson, int $file, LessonContent $content): BinaryFileResponse
    {
        $this->authorizeLesson($course, $lesson, $request->user());

        return $content->file($lesson, $file);
    }

    public function previewFile(string $tenant, Course $course, CourseLesson $lesson, int $file, LessonContent $content): BinaryFileResponse
    {
        abort_unless(CourseResource::canEdit($course), 403);

        return $content->file($lesson, $file, previewAttachments: true);
    }

    private function authorizeLesson(Course $course, CourseLesson $lesson, User $user): bool
    {
        abort_unless($course->is_published, 404);

        $enrolled = $this->isEnrolled($course, $user);
        abort_unless($enrolled || $lesson->is_preview, 403);

        return $enrolled;
    }

    private function canBrowseCatalog(User $user): bool
    {
        $roles = $user->getRoleNames();

        return $user->isSuperAdmin() || ($roles->isNotEmpty() && ! $roles->contains(DefaultRoles::LEARNER));
    }

    private function isEnrolled(Course $course, User $user): bool
    {
        return $course->enrollments()->active()->where('user_id', $user->getKey())->exists();
    }
}
