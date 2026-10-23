<?php

namespace Modules\Site\Filament\Resources\SitePages\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Site\Filament\Resources\SitePages\PageResource;
use Modules\Site\Widgets\Widget;
use Modules\Site\Widgets\Widgets;

/**
 * «صفحه‌ساز»: a page's widgets, managed apart from its settings (ADR 0011);
 * only the `content` column is edited here. Reached from the list's row action.
 */
class PageBuilder extends EditRecord
{
    protected static string $resource = PageResource::class;

    public function getTitle(): string|Htmlable
    {
        return "صفحه‌ساز «{$this->getRecord()->title}»";
    }

    public function getBreadcrumb(): string
    {
        return 'صفحه‌ساز';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Builder::make('content')
                ->hiddenLabel()
                ->blocks(Widgets::blocks())
                ->addActionLabel('افزودن ویجت')
                // Adding and editing a widget happen in a modal only: the
                // list shows each widget's header (title, actions) and no
                // body, so there is nothing to fold or expand.
                ->blockPreviews()
                ->extraAttributes(['class' => '[&_.fi-fo-builder-item-content]:hidden'])
                // No «درج» (insert between) button on hover; new widgets go at the end.
                ->addBetweenAction(fn (Action $action): Action => $action->hidden())
                ->blockNumbers(false)
                ->extraItemActions([
                    self::toggleVisibilityAction(),
                ])
                ->default([])
                ->columnSpanFull(),
        ]);
    }

    /**
     * Show / hide a widget on the site from its header, without the modal.
     * Like every change here, it takes effect on save.
     */
    private static function toggleVisibilityAction(): Action
    {
        $isVisible = fn (array $arguments, Builder $component): bool => Widget::isVisible($component->getRawState()[$arguments['item']]['data'] ?? []);

        return Action::make('toggleVisibility')
            ->label(fn (array $arguments, Builder $component): string => $isVisible($arguments, $component) ? 'مخفی کردن' : 'نمایش')
            ->icon(fn (array $arguments, Builder $component): Heroicon => $isVisible($arguments, $component) ? Heroicon::OutlinedEye : Heroicon::OutlinedEyeSlash)
            ->color(fn (array $arguments, Builder $component): string => $isVisible($arguments, $component) ? 'gray' : 'warning')
            ->iconButton()
            ->action(function (array $arguments, Builder $component) use ($isVisible): void {
                $state = $component->getRawState();
                $state[$arguments['item']]['data']['is_visible'] = ! $isVisible($arguments, $component);
                $component->rawState($state);

                // As Builder's own item actions do: in the browser only the
                // re-rendered parts of the form are sent back.
                $component->callAfterStateUpdated();
                $component->shouldPartiallyRenderAfterActionsCalled() ? $component->partiallyRender() : null;
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('مشاهده در سایت')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => $this->getRecord()->url(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->getRecord()->is_published),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['content'] = Widgets::withDefaults($data['content'] ?? []);

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
