<?php

namespace Modules\Tutorial\Filament\Pages;

use App\Auth\Impersonation;
use App\Filament\Support\IndependentOfTenant;
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
use Modules\Tutorial\Services\TutorialFlowchart;
use Modules\Tutorial\Services\TutorialMode;
use Modules\Tutorial\Services\TutorialProgress;
use Modules\Tutorial\Services\TutorialReset;
use RuntimeException;

/**
 * «آموزش گام‌به‌گام» (ADR 0040): pick a scenario, follow its missions in
 * order, pause, or start again, which deletes what the tutorial made.
 * Super admins only: missions sign in as other accounts.
 */
class StepByStepTutorial extends Page implements IndependentOfTenant
{
    protected string $view = 'tutorial::filament.step-by-step-tutorial';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'آموزش گام‌به‌گام';

    protected static ?string $slug = 'tutorial';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function start(string $scenario): void
    {
        abort_if(app(Scenarios::class)->find($scenario) === null || $this->run() !== null, 404);

        app(TutorialMode::class)->enter(TutorialRun::query()->create(['user_id' => auth()->id(), 'scenario' => $scenario, 'completed_missions' => []]));
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

    private function run(): ?TutorialRun
    {
        return TutorialRun::query()->where('user_id', auth()->id())->first();
    }
}
