<?php

namespace Modules\Site\Filament\Resources\SitePages\Schemas;

use App\Models\Tenant;
use App\PublicSite\PublicSite;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Site\Filament\Resources\SitePages\PageResource;
use Modules\Site\Models\Page;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    ->label(config('sarasystem.tenant_label'))
                    ->options(fn (?Page $record): array => $record
                        ? [$record->tenant_id => $record->tenant->name]
                        : PageResource::creatableTenantOptions())
                    ->default(fn (): ?int => Tenant::current()?->getKey())
                    ->required()
                    ->live()
                    // A page stays in its tenant; make a new one for another tenant.
                    ->disabledOn('edit'),
                TextInput::make('title')
                    ->label('عنوان')
                    ->required()
                    ->maxLength(255),
                TextInput::make('path')
                    ->label('آدرس')
                    ->placeholder('/about-us')
                    ->helperText(fn (Get $get): string => self::urlHint($get('tenant_id'), $get('path')))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('path', Page::normalizePath($state)))
                    ->rules([
                        fn (Get $get, ?Page $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $tenant = Tenant::find($get('tenant_id') ?? $record?->tenant_id);

                            if ($tenant && ($problem = Page::pathProblem(Page::normalizePath($value), $tenant, $record))) {
                                $fail($problem);
                            }
                        },
                    ])
                    ->dehydrateStateUsing(fn (?string $state): string => Page::normalizePath($state))
                    ->extraInputAttributes(['dir' => 'ltr']),
                Toggle::make('is_published')
                    ->label('منتشر شده')
                    ->helperText('صفحه‌ی منتشرنشده برای بازدیدکننده‌ها پیدا نمی‌شود.'),
            ]);
    }

    private static function urlHint(mixed $tenantId, ?string $path): string
    {
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if ($tenant === null) {
            return 'مثلاً /about-us؛ آدرس «/» صفحه‌ی خانه‌ی '.config('sarasystem.tenant_label').' می‌شود.';
        }

        return 'آدرس نهایی: '.PublicSite::url($tenant, Page::normalizePath($path)).' — «/» صفحه‌ی خانه می‌شود.';
    }
}
