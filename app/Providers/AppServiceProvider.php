<?php

namespace App\Providers;

use App\Documents\ApplicantDocuments;
use App\Documents\NoApplicantDocuments;
use App\Models\User;
use App\Sms\LogSmsSender;
use App\Sms\SmsSender;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;
use Nwidart\Modules\Facades\Module;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn (): SmsSender => match (config('sms.driver')) {
            'log' => new LogSmsSender,
            default => throw new InvalidArgumentException('Unknown SMS driver ['.config('sms.driver').'].'),
        });

        // The Document module binds its own (ADR 0019); bindIf, whichever registers first.
        $this->app->bindIf(ApplicantDocuments::class, NoApplicantDocuments::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Shield-generated policies for resources whose models live
        // outside App\Models (e.g. Spatie's Role), which Laravel can't discover.
        FilamentShield::enforcePolicies();

        // Module Inertia pages are named "Module::Page"; let Inertia's page
        // finder (used by tests) resolve them inside the module.
        $this->app->afterResolving('inertia.view-finder', function (FileViewFinder $finder): void {
            foreach (Module::allEnabled() as $module) {
                $finder->addNamespace($module->getStudlyName(), $module->getExtraPath('resources/js/Pages'));
            }
        });

        // Installation-wide super admin: holds the super_admin role in any
        // tenant (ADR 0007).
        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);
    }
}
