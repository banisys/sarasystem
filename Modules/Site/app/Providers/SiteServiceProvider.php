<?php

namespace Modules\Site\Providers;

use App\Models\Tenant;
use App\PublicSite\PublicSite;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Modules\Site\Models\Page;
use Modules\Site\PublicSite\SitePageResolver;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SiteServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Site';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'site';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->tag(SitePageResolver::class, PublicSite::RESOLVER_TAG);
    }

    public function boot(): void
    {
        parent::boot();

        // Every tenant has a home page in its page list (ADR 0010).
        Tenant::created(fn (Tenant $tenant) => Page::ensureHomeFor($tenant));

        // A new tenant slug must not hide the pages under /{slug} of
        // whichever tenant is (or becomes) primary (ADR 0010).
        Tenant::reserveSlugsUsing(fn (): array => Page::withoutTenantScope()
            ->pluck('path')
            ->map(fn (string $path): string => Str::before(ltrim($path, '/'), '/'))
            ->filter()
            ->unique()
            ->all());
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
