<?php

namespace App\Filament\Concerns;

use App\Models\PageHelp;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * A help icon beside the page's title (ADR 0065). It opens a modal with the
 * page's help: in a textarea for those who may edit it, as plain text for
 * everyone else who can open the page.
 */
trait HasPageHelp
{
    /**
     * Identifies the page's text in the database, so it must not change.
     */
    abstract protected function pageHelpKey(): string;

    /**
     * The text until someone edits it, with line breaks kept as written.
     */
    abstract protected function defaultPageHelp(): string;

    public function getHeading(): string|Htmlable|null
    {
        return new HtmlString(view('filament.admin.page-help-heading', [
            'heading' => parent::getHeading(),
            'action' => $this->getAction('pageHelp', isMounting: false),
        ])->render());
    }

    public function pageHelpAction(): Action
    {
        $action = Action::make('pageHelp')
            ->iconButton()
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->color('gray')
            ->label('راهنمای این صفحه')
            ->tooltip('راهنمای این صفحه')
            ->modalHeading(fn (): string => 'راهنمای صفحه‌ی «'.strip_tags((string) parent::getHeading()).'»')
            ->modalWidth(Width::ThreeExtraLarge);

        if (! auth()->user()?->can(PageHelp::EDIT_PERMISSION)) {
            return $action
                ->modalContent(fn () => view('filament.admin.page-help-text', ['text' => $this->currentPageHelp()]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('بستن');
        }

        return $action
            ->fillForm(fn (): array => ['body' => $this->currentPageHelp()])
            ->schema([
                Textarea::make('body')
                    ->hiddenLabel()
                    ->rows(20)
                    ->maxLength(20000)
                    ->helperText('اگر متن را خالی کنید، متن پیش‌فرض این صفحه برمی‌گردد.'),
            ])
            ->modalSubmitActionLabel('ذخیره')
            ->action(function (array $data): void {
                PageHelp::saveText($this->pageHelpKey(), $data['body'] ?? null, $this->defaultPageHelp(), auth()->user());

                Notification::make()->title('راهنمای صفحه ذخیره شد.')->success()->send();
            });
    }

    private function currentPageHelp(): string
    {
        return PageHelp::textFor($this->pageHelpKey(), $this->defaultPageHelp());
    }
}
