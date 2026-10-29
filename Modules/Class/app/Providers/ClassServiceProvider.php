<?php

namespace Modules\Class\Providers;

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
}
