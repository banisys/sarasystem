<?php

namespace App\Providers;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
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
    }
}
