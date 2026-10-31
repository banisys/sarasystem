<?php

namespace Modules\Class\Providers;

use App\Authorization\DefaultRoles;
use App\Guardians\Guardians;
use App\Models\Tenant;
use App\Models\User;
use App\UserPanel\UserPanel;
use Modules\Class\Services\DefaultClassRoles;
use Modules\Class\Services\MyClasses;
use Modules\Class\Services\PlanForecast;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ClassServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Class';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'class';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // Remembers the forecasts it worked out during a request (ADR 0025).
        $this->app->scoped(PlanForecast::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The screens' permissions for the default roles (ADR 0039).
        app(DefaultRoles::class)->grant([
            DefaultRoles::ORG_ADMIN => [...DefaultRoles::manage('Term'), ...DefaultRoles::manage('ClassRole', reorder: true), ...DefaultRoles::manage('Subject', reorder: true), ...DefaultRoles::manage('TeachingPlan')],
            DefaultRoles::EDUCATION_OFFICER => [...DefaultRoles::manage('Classroom'), ...DefaultRoles::manage('ClassSession'), ...DefaultRoles::manage('Holiday')],
        ]);
        app(DefaultRoles::class)->installing(fn () => app(DefaultClassRoles::class)->install());

        // «کلاس‌های من» for members and teachers of classes (ADR 0026), and
        // «کلاس‌های …» of each ward in classes for their guardians (ADR 0027).
        UserPanel::navigationUsing('classes', function (User $user, Tenant $tenant): array {
            $myClasses = app(MyClasses::class);
            $wards = app(Guardians::class)->wardsIn($user, $tenant)->filter(fn (User $ward): bool => $myClasses->hasAny($ward));

            return [
                ...($myClasses->hasAny($user) ? [['title' => 'کلاس‌های من', 'url' => route('my-classes.index')]] : []),
                ...$wards->map(fn (User $ward): array => ['title' => 'کلاس‌های '.$ward->name, 'url' => route('my-classes.index', ['for' => $ward->getKey()])])->values()->all(),
            ];
        });
    }
}
