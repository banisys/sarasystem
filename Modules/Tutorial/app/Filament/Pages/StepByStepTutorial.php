<?php

namespace Modules\Tutorial\Filament\Pages;

use App\Auth\Impersonation;
use App\Filament\Support\IndependentOfTenant;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\Scenario;
use Modules\Tutorial\Scenarios\Scenarios;
use Modules\Tutorial\Services\TutorialAutoComplete;
use Modules\Tutorial\Services\TutorialFlowchart;
use Modules\Tutorial\Services\TutorialMode;
use Modules\Tutorial\Services\TutorialProgress;
use Modules\Tutorial\Services\TutorialReset;
use RuntimeException;
use UnitEnum;

/**
 * «آموزش گام‌به‌گام» (ADR 0040): pick a scenario, follow its missions in
 * order, pause, or start again, which deletes what the tutorial made.
 * Super admins only: missions sign in as other accounts.
 */
class StepByStepTutorial extends Page implements IndependentOfTenant
{
    /** The menu group of the tutorial and the sample data (ADR 0046). */
    public const string NAVIGATION_GROUP = 'آموزش سامانه';

    protected string $view = 'tutorial::filament.step-by-step-tutorial';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = self::NAVIGATION_GROUP;

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'آموزش گام‌به‌گام';

    protected static ?string $slug = 'tutorial';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function start(string $scenario): void
    {
        abort_if($this->run() !== null, 404);

        app(TutorialMode::class)->enter($this->newRun($scenario));
    }

    public function resume(): void
    {
        app(TutorialMode::class)->enter($this->run() ?? abort(404));
    }

    public function pause(): void
    {
        app(TutorialMode::class)->leave();
    }

    /**
     * «نمای فلوچارت»: the scenario's missions as a swimlane diagram, with
     * the run's progress when it is the scenario being followed.
     */
    public function flowchartAction(): Action
    {
        return Action::make('flowchart')
            ->label('نمای فلوچارت')
            ->icon(Heroicon::OutlinedShare)
            ->color('gray')
            ->modalHeading(fn (array $arguments): string => 'نمای فلوچارت: '.$this->flowchartScenario($arguments)->title())
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('بستن')
            ->modalContent(function (array $arguments): View {
                $scenario = $this->flowchartScenario($arguments);
                $run = $this->run();
                $run = $run?->scenario === $scenario->key() ? $run : null;
                $current = $run ? app(TutorialProgress::class)->refresh($run) : null;

                return view('tutorial::filament.flowchart', [
                    'chart' => app(TutorialFlowchart::class)->layout($scenario, $run, $current),
                    'hasRun' => $run !== null,
                ]);
            });
    }

    /**
     * «انجام خودکار همه‌ی گام‌ها»: the remaining missions are done with the
     * scenario's sample data, in tutorial mode, so «شروع دوباره» deletes it.
     * With no run yet (e.g. after starting again) it starts one.
     */
    public function autoCompleteAction(): Action
    {
        return Action::make('autoComplete')
            ->label('انجام خودکار همه‌ی گام‌ها')
            ->icon(Heroicon::OutlinedBolt)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('انجام خودکار همه‌ی گام‌ها')
            ->modalDescription('گام‌های باقی‌مانده یکی‌یکی با داده‌های نمونه‌ی سناریو انجام و در پایگاه داده ذخیره می‌شوند، همان‌طور که اگر خودتان انجام می‌دادید. این داده‌ها جزو آموزش‌اند و با «شروع دوباره» پاک می‌شوند.')
            ->modalSubmitActionLabel('انجام بده')
            ->disabled(fn (): bool => Impersonation::isActive())
            ->action(function (array $arguments, TutorialAutoComplete $autoComplete): void {
                $run = $this->run() ?? $this->newRun((string) ($arguments['scenario'] ?? ''));
                abort_unless(app(TutorialProgress::class)->scenario($run)->canAutoComplete(), 404);

                try {
                    $count = $autoComplete->complete($run, auth()->user());
                } catch (RuntimeException $exception) {
                    Notification::make()->danger()->title('انجام خودکار متوقف شد')->body($exception->getMessage())->persistent()->send();

                    return;
                }

                Notification::make()->success()->title('همه‌ی گام‌ها انجام شد')->body(number_format($count).' گام به‌طور خودکار انجام شد.')->send();
                // The tenant menu now lists the new branch.
                $this->redirect(static::getUrl(tenant: Tenant::primary()));
            });
    }

    public function resetAction(): Action
    {
        return Action::make('reset')
            ->label('شروع دوباره')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('شروع دوباره‌ی آموزش')
            ->modalDescription(fn (): string => 'هر چه در این آموزش ساخته شده ('.number_format($this->run()?->records()->count() ?? 0).' رکورد) پاک می‌شود و می‌توانید سناریو را از اول شروع کنید. آنچه پیش از آموزش وجود داشت تغییری نمی‌کند.')
            ->modalSubmitActionLabel('پاک کن و از اول')
            ->disabled(fn (): bool => Impersonation::isActive())
            ->action(function (TutorialReset $reset): void {
                $run = $this->run() ?? abort(404);

                try {
                    $count = $reset->reset($run);
                } catch (RuntimeException $exception) {
                    Notification::make()->danger()->title('آموزش پاک نشد')->body('رکوردهایی که در آموزش ساخته شده‌اند هنوز جای دیگری استفاده می‌شوند.')->send();
                    report($exception);

                    return;
                }

                Notification::make()->success()->title('آموزش از اول')->body(number_format($count).' رکورد آموزشی پاک شد.')->send();
                // The page's tenant (e.g. the tutorial's branch) may be gone.
                $this->redirect(static::getUrl(tenant: Tenant::primary()));
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $run = $this->run();
        $progress = app(TutorialProgress::class);
        $current = $run ? $progress->refresh($run) : null;

        return [
            'scenarios' => app(Scenarios::class)->all(),
            'run' => $run,
            'scenario' => $run ? $progress->scenario($run) : null,
            'current' => $current,
            'isActive' => $run !== null && app(TutorialMode::class)->run()?->is($run),
            'impersonating' => Impersonation::isActive(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function flowchartScenario(array $arguments): Scenario
    {
        return app(Scenarios::class)->find((string) ($arguments['scenario'] ?? '')) ?? abort(404);
    }

    private function newRun(string $scenario): TutorialRun
    {
        abort_unless(app(Scenarios::class)->find($scenario)?->isAvailable(), 404);

        return TutorialRun::query()->create(['user_id' => auth()->id(), 'scenario' => $scenario, 'completed_missions' => []]);
    }

    private function run(): ?TutorialRun
    {
        return TutorialRun::query()->where('user_id', auth()->id())->first();
    }
}
