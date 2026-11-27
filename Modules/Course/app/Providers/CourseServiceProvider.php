<?php

namespace Modules\Course\Providers;

use App\Authorization\DefaultRoles;
use App\PublicSite\PublicSite;
use App\Support\SessionLessonLibrary;
use App\UserPanel\UserPanel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseLessons;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseParticipants;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseSections;
use Modules\Course\Integrations\CourseExamSource;
use Modules\Course\Models\Course;
use Modules\Course\Services\LessonContent;
use Modules\Exam\Support\ExamContexts;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CourseServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Course';

    protected string $nameLower = 'course';

    protected array $providers = [RouteServiceProvider::class];

    public function register(): void
    {
        parent::register();

        $this->app->bind(SessionLessonLibrary::class, LessonContent::class);
    }

    public function boot(): void
    {
        parent::boot();

        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER,
            fn (): HtmlString => new HtmlString('<div id="course-page-actions"></div>'),
            scopes: [ManageCourseLessons::class, ManageCourseParticipants::class, ManageCourseSections::class],
        );

        if (Module::isEnabled('Exam')) {
            app(ExamContexts::class)->register(CourseExamSource::TYPE, app(CourseExamSource::class));
        }

        app(DefaultRoles::class)->grant([
            DefaultRoles::EDUCATION_OFFICER => [
                ...DefaultRoles::manage('Course'),
                ...DefaultRoles::manage('CourseCategory'),
                ...DefaultRoles::manage('CourseTeacher'),
            ],
        ]);

        PublicSite::navigationUsing('courses', fn (): array => Course::query()->published()->exists()
            ? [['title' => 'دوره‌های آموزشی', 'url' => route('courses.catalog'), 'new_tab' => false, 'children' => []]]
            : []);

        UserPanel::navigationUsing('courses', fn (): array => Course::query()->published()->exists()
            ? [['title' => 'دوره‌های من', 'url' => route('courses.index'), 'icon' => 'academic']]
            : [], requiresTenant: false);
    }
}
