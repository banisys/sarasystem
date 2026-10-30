<?php

namespace App\Filament\Support;

use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PanelPageContext
{
    public function __construct(private Request $request, private Router $router) {}

    public function isIndependentOfTenant(): bool
    {
        $route = $this->request->route();

        // Sidebar refreshes run on Livewire's endpoint, not the page's GET route.
        if (Livewire::isLivewireRequest()) {
            try {
                $route = $this->router->getRoutes()->match(Request::create(Livewire::originalUrl()));
            } catch (HttpException) {
                return false;
            }
        }

        $page = $route?->getControllerClass();

        if (! is_string($page)) {
            return false;
        }

        return is_a($page, IndependentOfTenant::class, true)
            || (is_subclass_of($page, ResourcePage::class)
                && is_a($page::getResource(), IndependentOfTenant::class, true));
    }
}
