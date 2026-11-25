<?php

namespace Modules\Course\Providers;

use App\Authorization\DefaultRoles;
use App\PublicSite\PublicSite;
use App\UserPanel\UserPanel;
use Modules\Course\Integrations\CourseExamSource;
use Modules\Course\Models\Course;
use Modules\Exam\Support\ExamContexts;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CourseServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Course';

    protected string $nameLower = 'course';

    protected array $providers = [RouteServiceProvider::class];

    public function boot(): void
    {
        parent::boot();

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
            ? [['title' => 'دوره‌های آموزشی', 'url' => route('courses.index'), 'icon' => 'academic']]
            : [], requiresTenant: false);
    }
}
