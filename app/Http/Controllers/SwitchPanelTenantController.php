<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Filament\Pages\BasePage;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Target of the admin panel's tenant menu (see the tenant-menu-same-page
 * render hook): opens the page the user was on, in the tenant they switch to.
 * Filament's tenant middleware has already checked access to that tenant and
 * made it current. Pages that need a record (edit / view) and pages the user
 * can't open there fall back to the closest parent page, then the dashboard.
 */
class SwitchPanelTenantController extends Controller
{
    public function __invoke(Request $request, Router $router): RedirectResponse
    {
        $panel = Filament::getCurrentPanel();
        $home = $panel->getUrl(Filament::getTenant());

        $segments = array_values(array_filter(
            explode('/', $request->string('page')->toString()),
            fn (string $segment): bool => ! in_array($segment, ['', '.', '..'], true),
        ));

        for (; $segments !== []; array_pop($segments)) {
            $url = $home.'/'.implode('/', $segments);

            if ($this->isOpenablePage($router, $url, $panel->getId())) {
                return redirect($url);
            }
        }

        return redirect($home);
    }

    /**
     * A page of this panel whose only route parameter is the tenant, and
     * which the user may open in the current tenant.
     */
    private function isOpenablePage(Router $router, string $url, string $panelId): bool
    {
        try {
            $route = $router->getRoutes()->match(Request::create($url));
        } catch (HttpException) {
            return false;
        }

        $page = $route->getControllerClass();

        return Str::startsWith((string) $route->getName(), "filament.{$panelId}.")
            && $route->parameterNames() === ['tenant']
            && is_string($page)
            && is_subclass_of($page, BasePage::class)
            && $page::canAccess()
            && $this->canOpenResourcePage($page);
    }

    /**
     * Resource pages check their resource's policy when they mount, not in
     * canAccess(), so the same checks are repeated here.
     *
     * @param  class-string<BasePage>  $page
     */
    private function canOpenResourcePage(string $page): bool
    {
        if (! is_subclass_of($page, ResourcePage::class)) {
            return true;
        }

        $resource = $page::getResource();

        return $resource::canAccess()
            && (! is_subclass_of($page, CreateRecord::class) || $resource::canCreate());
    }
}
