<?php

namespace Modules\Course\Http\Controllers;

use App\Http\Controllers\Controller;
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

        if ($branch !== null) {
            abort_unless($tenants->where('slug', $branch)->exists(), 404);

            return to_route('courses.catalog', array_filter([
                ...$request->query(),
                'catalogCourse' => $id,
            ], fn (mixed $value): bool => $value !== null));
        }

        $layout = PublicSite::layoutProps($tenants->where('is_primary', true)->first());

        return $id === null
            ? $this->index($request, $layout)
            : $this->show($layout, (int) $id);
    }

    public function open(Request $request): RedirectResponse
    {
        $course = Course::query()->published()->findOrFail($request->route('catalogCourse'));

        return to_route('courses.show', $course);
    }

    /** @param array<string, mixed> $layout */
    private function index(Request $request, array $layout): Response
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
                'url' => route('courses.catalog', $course),
            ]);

        return Inertia::render('Course::Catalog', [
            ...$layout,
            'courses' => $courses,
            'categories' => $tree->catalogOptions(),
            'filters' => ['search' => $search, 'category' => $category ?: ''],
            'catalogUrl' => route('courses.catalog'),
        ]);
    }

    /** @param array<string, mixed> $layout */
    private function show(array $layout, int $id): Response
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
            'catalogUrl' => route('courses.catalog'),
            'studyUrl' => route('courses.show', $course),
        ]);
    }
}
