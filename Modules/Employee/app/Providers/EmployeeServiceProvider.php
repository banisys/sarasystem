<?php

namespace Modules\Employee\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Nwidart\Modules\Support\ModuleServiceProvider;

class EmployeeServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Employee';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'employee';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // The public form has no captcha: a few submissions, and a few SMS
        // codes sent or checked, per hour per IP (ADR 0014).
        RateLimiter::for('careers', fn (Request $request): Limit => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('careers-mobile', fn (Request $request): Limit => Limit::perHour(10)->by($request->ip()));
    }
}
