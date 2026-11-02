<?php

namespace Modules\Tutorial\Providers;

use App\PublicSite\HiddenRecords;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Livewire\Livewire;
use Modules\Tutorial\Livewire\TutorialBar;
use Modules\Tutorial\Models\TutorialRecord;
use Modules\Tutorial\Scenarios\LanguageInstitute;
use Modules\Tutorial\Scenarios\Scenarios;
use Modules\Tutorial\Scenarios\School;
use Modules\Tutorial\Scenarios\SystemAdministration;
use Modules\Tutorial\Services\TutorialGuide;
use Modules\Tutorial\Services\TutorialMode;
use Modules\Tutorial\Services\TutorialRecorder;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TutorialServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Tutorial';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'tutorial';

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

        $this->app->singleton(Scenarios::class);
        $this->app->scoped(TutorialMode::class);
    }

    public function boot(): void
    {
        parent::boot();

        app(Scenarios::class)->register(SystemAdministration::class);
        app(Scenarios::class)->register(LanguageInstitute::class);
        app(Scenarios::class)->register(School::class);

        // Every row made in tutorial mode belongs to the run (ADR 0040).
        Event::listen('eloquent.created: *', fn (string $event, array $models) => app(TutorialRecorder::class)->created($models[0]));

        // The public site doesn't show visitors what others made in a tutorial.
        app(HiddenRecords::class)->hideUsing(function (string $model): array {
            $ownRun = app(TutorialMode::class)->run();

            return TutorialRecord::query()
                ->where('model_type', $model)
                ->when($ownRun, fn ($query) => $query->where('run_id', '!=', $ownRun->getKey()))
                ->pluck('model_id')
                ->map(fn (string $id): int|string => ctype_digit($id) ? (int) $id : $id)
                ->all();
        });

        // The bar on admin-panel pages, and its data for the Inertia ones.
        Livewire::component('tutorial-bar', TutorialBar::class);
        FilamentView::registerRenderHook(PanelsRenderHook::BODY_START, fn (): string => Blade::render('@livewire(\'tutorial-bar\')'));
        Inertia::share('tutorial', fn (): ?array => app(TutorialGuide::class)->data());
    }
}
