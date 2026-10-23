<?php

namespace Modules\Site\Filament\Resources\SiteMenus\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Site\Models\MenuItem;
use Modules\Site\Models\Page;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->required()
                    ->maxLength(255),
                Select::make('link_type')
                    ->label('لینک به')
                    ->options(MenuItem::linkTypes())
                    ->default(MenuItem::LINK_PAGE)
                    ->required()
                    ->live(),
                Select::make('page_id')
                    ->label('صفحه')
                    ->options(fn (): array => Page::query()->orderByRaw('path = ? desc', [Page::HOME_PATH])->orderBy('title')->get()
                        ->mapWithKeys(fn (Page $page): array => [$page->id => "{$page->title} ({$page->path})"])
                        ->all())
                    ->searchable()
                    ->helperText('اگر آدرس صفحه عوض شود، لینک هم با آن عوض می‌شود. صفحه‌ی منتشرنشده در منو نمایش داده نمی‌شود.')
                    ->visible(fn (Get $get): bool => $get('link_type') === MenuItem::LINK_PAGE)
                    ->required(fn (Get $get): bool => $get('link_type') === MenuItem::LINK_PAGE),
                TextInput::make('url')
                    ->label('آدرس')
                    ->placeholder('https://example.com')
                    ->helperText('همان‌طور که نوشته می‌شود استفاده می‌شود: https://…، مسیری از همین دامنه مثل /login، mailto: یا tel:')
                    ->maxLength(2048)
                    ->regex(MenuItem::URL_PATTERN)
                    ->validationMessages(['regex' => 'آدرس باید با https://، http://، /، #، mailto: یا tel: شروع شود.'])
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->visible(fn (Get $get): bool => $get('link_type') === MenuItem::LINK_URL)
                    ->required(fn (Get $get): bool => $get('link_type') === MenuItem::LINK_URL),
                Toggle::make('open_in_new_tab')
                    ->label('باز شدن در زبانه‌ی جدید')
                    ->default(false)
                    ->hidden(fn (Get $get): bool => $get('link_type') === MenuItem::LINK_NONE),
                Toggle::make('is_visible')
                    ->label('نمایش در سایت')
                    ->helperText('اگر خاموش باشد، زیرمنوی این آیتم هم نمایش داده نمی‌شود.')
                    ->default(true),
            ])
            ->columns(1);
    }

    /**
     * Only the field of the chosen link type is kept.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareData(array $data): array
    {
        $type = $data['link_type'] ?? null;

        return [
            ...$data,
            'page_id' => $type === MenuItem::LINK_PAGE ? ($data['page_id'] ?? null) : null,
            'url' => $type === MenuItem::LINK_URL ? ($data['url'] ?? null) : null,
            'open_in_new_tab' => $type !== MenuItem::LINK_NONE && ($data['open_in_new_tab'] ?? false),
        ];
    }
}
