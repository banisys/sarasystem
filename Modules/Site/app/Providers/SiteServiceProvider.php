<?php

namespace Modules\Site\Providers;

use App\Authorization\DefaultRoles;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\PublicSite\PublicSite;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Modules\Site\Filament\Actions\ChooseTenantMenuAction;
use Modules\Site\Models\Page;
use Modules\Site\Models\TenantMenu;
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

        // The screens' permissions for the default roles (ADR 0039).
        app(DefaultRoles::class)->grant([
            DefaultRoles::TENANT_MANAGER => [...DefaultRoles::manage('Menu'), ...DefaultRoles::manage('Page')],
        ]);

        // Every tenant has a home page in its page list (ADR 0010).
        Tenant::created(fn (Tenant $tenant) => Page::ensureHomeFor($tenant));

        // The home page made with the tenant goes with it, unless someone
        // has put content on it (then the tenant has data and is not deleted).
        Tenant::deleting(function (Tenant $tenant): void {
            Page::withoutTenantScope()
                ->where('tenant_id', $tenant->getKey())
                ->where('path', Page::HOME_PATH)
                ->get()
                ->filter(fn (Page $page): bool => blank($page->content))
                // A query delete: the model itself refuses to delete a home page.
                ->each(fn (Page $page) => Page::withoutTenantScope()->whereKey($page->getKey())->delete());
        });

        // A new tenant slug must not hide the pages under /{slug} of
        // whichever tenant is (or becomes) primary (ADR 0010).
        Tenant::reserveSlugsUsing(fn (): array => Page::withoutTenantScope()
            ->pluck('path')
            ->map(fn (string $path): string => Str::before(ltrim($path, '/'), '/'))
            ->filter()
            ->unique()
            ->all());

        // Each tenant's site header shows the menu chosen for it in the
        // tenants list (ADR 0012).
        TenantResource::addRecordActionsUsing('site-menu', fn (): array => [ChooseTenantMenuAction::make()]);
        PublicSite::navigationUsing('site-menu', fn (Tenant $tenant): array => TenantMenu::menuOf($tenant)?->publicItems() ?? []);
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
