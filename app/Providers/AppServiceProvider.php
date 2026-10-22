<?php

namespace App\Providers;

use App\Models\User;
use App\Sms\LogSmsSender;
use App\Sms\SmsSender;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

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
