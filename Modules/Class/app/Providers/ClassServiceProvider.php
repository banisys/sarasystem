<?php

namespace Modules\Class\Providers;

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
}
