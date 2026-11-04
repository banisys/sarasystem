<?php

namespace Modules\Class\Filament\Concerns;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Services\SessionCalendar;

/**
 * A session calendar on a Livewire component (ADR 0047): the view and date
 * in the URL and the calendar's schema component; a session links to its
 * page (ADR 0050). Used by a class's «جلسه‌ها» tab and a person's calendar
 * (ADR 0048).
 */
trait ShowsSessionCalendar
{
    /**
     * «روزانه», «هفتگی», «ماهانه» or «سالانه».
     */
    #[Url(as: 'sessions')]
    public string $sessionsView = 'month';

    /**
     * A day in the calendar's period (Y-m-d); empty is today.
     */
    #[Url(as: 'on')]
    public ?string $sessionsDate = null;

    /**
     * @return array<string, mixed>
     */
    abstract protected function calendarData(SessionCalendar $calendar, string $view, CarbonImmutable $date): array;

    /**
     * Whether the calendar offers «جلسه‌ی جبرانی / فوق‌العاده» (addSessionAction()).
     */
    protected function canAddSessions(): bool
    {
        return false;
    }

    protected function sessionCalendarComponent(): View
    {
        return View::make('class::filament.session-calendar')
            ->viewData(fn (SessionCalendar $calendar): array => [
                'calendar' => $this->calendarData($calendar, $this->currentView(), $this->calendarDate()),
                'views' => ['day' => 'روزانه', 'week' => 'هفتگی', 'month' => 'ماهانه', 'year' => 'سالانه'],
                'current' => $this->currentView(),
                'tones' => SessionCalendar::toneLabels(),
                'canAddSessions' => $this->canAddSessions(),
                'sessionUrl' => fn (int $session): string => ClassSessionResource::getUrl('view', ['record' => $session]),
                // A page renders the action modals itself; a relation manager without its table doesn't.
                'rendersModals' => ! $this instanceof Page,
            ])
            ->schema([
                // «برو به تاریخ»: picks the calendar's date directly.
                DatePicker::make('sessionsDate')
                    ->hiddenLabel()
                    ->jalali()
                    ->placeholder('برو به تاریخ')
                    ->prefixIcon(Heroicon::OutlinedCalendarDays)
                    ->closeOnDateSelection()
                    ->live(),
            ]);
    }

    public function showSessions(string $view, ?string $date = null): void
    {
        $this->sessionsView = in_array($view, SessionCalendar::VIEWS, true) ? $view : 'month';

        if ($date !== null) {
            $this->sessionsDate = $this->parseDate($date)?->toDateString();
        }
    }

    protected function currentView(): string
    {
        return in_array($this->sessionsView, SessionCalendar::VIEWS, true) ? $this->sessionsView : 'month';
    }

    protected function calendarDate(): CarbonImmutable
    {
        return $this->parseDate($this->sessionsDate) ?? today()->toImmutable();
    }

    protected function parseDate(?string $date): ?CarbonImmutable
    {
        // The date picker may send a time after the date.
        $date = is_string($date) ? substr($date, 0, 10) : null;

        if ($date === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        return rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', $date), report: false) ?: null;
    }
}
