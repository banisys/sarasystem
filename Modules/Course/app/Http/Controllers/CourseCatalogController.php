<?php

namespace Modules\Course\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\PublicSite\HiddenRecords;
use App\PublicSite\PublicSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseEnrollmentSettings;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;
use Modules\Course\Support\CourseCategoryTree;

class CourseCatalogController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $branch = $request->route('branch');
        $id = $request->route('catalogCourse');
        $tenants = app(HiddenRecords::class)->exclude(Tenant::query()->active());
        $tenant = $branch === null
            ? $tenants->where('is_primary', true)->first()
            : $tenants->where('slug', $branch)->first();

        abort_if($tenant === null, 404);

        if ($branch !== null && $tenant->is_primary) {
            return redirect()->route('courses.catalog', array_filter([
                ...$request->query(),
                'catalogCourse' => $id,
            ], fn (mixed $value): bool => $value !== null));
        }

        $layout = PublicSite::layoutProps($tenant);

        return $tenant->execute(fn (): Response => $id === null
            ? $this->index($request, $tenant, $layout)
            : $this->show($request, $tenant, $layout, (int) $id));
    }

    public function open(Request $request, Tenant $branch, int $catalogCourse): RedirectResponse
    {
        abort_unless($request->user()->belongsToTenant($branch), 403);

        $course = $branch->execute(fn (): Course => Course::query()->published()->findOrFail($catalogCourse));
        $request->session()->put(ResolveCurrentTenant::SESSION_KEY, $branch->getKey());

        return redirect()->route('courses.show', $course);
    }

    /** @param array<string, mixed> $layout */
    private function index(Request $request, Tenant $tenant, array $layout): Response
    {
        $category = max(0, $request->integer('category'));
        $search = mb_substr(trim((string) $request->string('search')), 0, 100);
        $tree = CourseCategoryTree::current(catalog: true);
        $freeEnrollmentEnabled = CourseEnrollmentSettings::current()->free_enabled;
        $courses = Course::query()->published()
            ->when($category, function (Builder $query) use ($tree, $category): void {
                $categoryIds = $tree->branchIds($category);

                $query->where(fn (Builder $courses): Builder => $courses->whereIn('course_category_id', $categoryIds)
                    ->orWhereHas('additionalCategories', fn (Builder $categories): Builder => $categories->whereKey($categoryIds)));
            })
            ->when($search !== '', fn (Builder $query): Builder => $query->whereLike('title', '%'.$search.'%'))
            ->with('category:id,title')
            ->withCount('lessons')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Course $course): array => [
                ...$course->only('id', 'title', 'lessons_count'),
                'allows_self_enrollment' => $freeEnrollmentEnabled && $course->allows_self_enrollment,
                'category' => $course->category?->title,
                'url' => PublicSite::url($tenant, '/courses/'.$course->getKey()),
            ]);

        return Inertia::render('Course::Catalog', [
            ...$layout,
            'courses' => $courses,
            'categories' => $tree->catalogOptions(),
            'filters' => ['search' => $search, 'category' => $category ?: ''],
            'catalogUrl' => PublicSite::url($tenant, '/courses'),
        ]);
    }

    /** @param array<string, mixed> $layout */
    private function show(Request $request, Tenant $tenant, array $layout, int $id): Response
    {
        $course = Course::query()->published()->with([
            'category:id,title',
            'instructors.user:id,name',
            'sections.lessons:id,course_section_id,title,duration_minutes,is_preview',
        ])->findOrFail($id);

        return Inertia::render('Course::CatalogCourse', [
            ...$layout,
            'course' => [
                ...$course->only('title', 'description'),
                'allows_self_enrollment' => $course->allows_self_enrollment && CourseEnrollmentSettings::current()->free_enabled,
                'category' => $course->category?->title,
                'instructors' => $course->instructors->pluck('user.name')->all(),
            ],
            'sections' => $course->sections->map(fn (CourseSection $section): array => [
                ...$section->only('id', 'title'),
                'lessons' => $section->lessons->map(fn (CourseLesson $lesson): array => $lesson->only('id', 'title', 'duration_minutes', 'is_preview')),
            ]),
            'catalogUrl' => PublicSite::url($tenant, '/courses'),
            'studyUrl' => $request->user() === null || $request->user()->belongsToTenant($tenant)
                ? route('courses.catalog.open', [$tenant->slug, $course]) : null,
        ]);
    }
}
