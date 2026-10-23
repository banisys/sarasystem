<?php

namespace Modules\Site\Filament\Resources\SitePages\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Site\Filament\Resources\SitePages\PageResource;
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
                ->collapsible()
                ->cloneable()
                ->blockNumbers(false)
                ->default([])
                ->columnSpanFull(),
        ]);
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

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
