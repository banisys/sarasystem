<?php

namespace Modules\Course\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Course\Integrations\CourseExamSource;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseEnrollmentSettings;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;
use Nwidart\Modules\Facades\Module;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $onlyMine = $request->boolean('mine');
        $freeEnrollmentEnabled = CourseEnrollmentSettings::current()->free_enabled;
        $myActiveEnrollment = fn (Builder $query): Builder => $query->active()->where('user_id', $request->user()->getKey());

        $courses = Course::query()->published()
            ->when($onlyMine, fn (Builder $query): Builder => $query->whereHas('enrollments', $myActiveEnrollment))
            ->with('category:id,title')
            ->withExists(['enrollments as enrolled' => $myActiveEnrollment])
            ->withCount('lessons')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Course $course): array => [
                ...$course->only('title', 'enrolled', 'lessons_count'),
                'allows_self_enrollment' => $freeEnrollmentEnabled && $course->allows_self_enrollment,
                'category' => $course->category?->title,
                'url' => route('courses.show', $course),
            ]);

        return Inertia::render('Course::Index', ['courses' => $courses, 'onlyMine' => $onlyMine]);
    }

    public function show(Request $request, Course $course): Response
    {
        abort_unless($course->is_published, 404);

        $user = $request->user();
        $enrollment = $course->enrollments()->where('user_id', $user->getKey())->first();
        $enrolled = $this->isEnrolled($course, $user);
        $course->load(['category:id,title', 'instructors.user:id,name', 'sections.lessons' => fn (HasMany $lessons) => $lessons
            ->select(['id', 'course_section_id', 'title', 'duration_minutes', 'is_preview'])
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

    public function lesson(Request $request, Course $course, CourseLesson $lesson): Response
    {
        $enrolled = $this->authorizeLesson($course, $lesson, $request->user());
        $lesson->load('media');
        $video = $lesson->getFirstMedia('video');
        $neighbours = $course->sections()->with('lessons:id,course_section_id,is_preview')->get()
            ->flatMap->lessons->filter(fn (CourseLesson $item): bool => $enrolled || $item->is_preview)->values();
        $position = $neighbours->search(fn (CourseLesson $item): bool => $item->is($lesson));

        return Inertia::render('Course::Lesson', [
            'course' => $course->only('id', 'title'),
            'lesson' => $lesson->only('id', 'title', 'body', 'duration_minutes'),
            'video' => $video ? route('courses.lesson.file', [$course, $lesson, $video->getKey()]) : null,
            'attachments' => $lesson->getMedia('attachments')->map(fn (Media $file): array => [
                'name' => $file->file_name,
                'url' => route('courses.lesson.file', [$course, $lesson, $file->getKey()]),
            ])->values(),
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

    public function file(Request $request, Course $course, CourseLesson $lesson, int $file): BinaryFileResponse
    {
        $this->authorizeLesson($course, $lesson, $request->user());

        $media = $lesson->media()->whereIn('collection_name', ['video', 'attachments'])->findOrFail($file);
        $path = $media->getPath();
        abort_unless(File::isFile($path), 404);

        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        return $media->collection_name === 'video' && in_array($media->mime_type, CourseLesson::VIDEO_TYPES, true)
            ? response()->file($path, [...$headers, 'Content-Type' => $media->mime_type])
            : response()->download($path, null, [...$headers, 'Content-Type' => 'application/octet-stream']);
    }

    private function authorizeLesson(Course $course, CourseLesson $lesson, User $user): bool
    {
        abort_unless($course->is_published, 404);

        $enrolled = $this->isEnrolled($course, $user);
        abort_unless($enrolled || $lesson->is_preview, 403);

        return $enrolled;
    }

    private function isEnrolled(Course $course, User $user): bool
    {
        return $course->enrollments()->active()->where('user_id', $user->getKey())->exists();
    }
}
