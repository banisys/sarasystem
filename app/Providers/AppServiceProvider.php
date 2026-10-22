<?php

namespace App\Providers;

use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Shield-generated policies for resources whose models live
        // outside App\Models (e.g. Spatie's Role), which Laravel can't discover.
        FilamentShield::enforcePolicies();

        // Installation-wide super admin (a flag, not a role: with permission
        // teams every role is held within a single tenant).
        Gate::before(fn (User $user): ?bool => $user->is_super_admin ? true : null);
    }
}
