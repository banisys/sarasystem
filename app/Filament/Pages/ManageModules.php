<?php

namespace App\Filament\Pages;

use App\Features\ModuleChangeRefused;
use App\Features\ModuleManager;
use App\Filament\Support\IndependentOfTenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Super-admin list of the installation's modules (features) with a switch for
 * each one (ADR 0042). It lives in the core, so no module can take it away.
 */
class ManageModules extends Page implements HasTable, IndependentOfTenant
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.manage-modules';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 96;

    protected static ?string $title = 'ماژول‌ها';

    protected static ?string $slug = 'modules';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => app(ModuleManager::class)->all()->map(
                fn (array $module): array => [...$module, 'requires_label' => implode('، ', $module['requires'])],
            ))
            ->columns([
                TextColumn::make('name')->label('ماژول')->weight('bold'),
                TextColumn::make('description')->label('توضیح')->wrap()->placeholder('—'),
                TextColumn::make('requires_label')->label('نیازمند')->placeholder('—'),
                IconColumn::make('enabled')->label('فعال')->boolean()->alignCenter(),
            ])
            ->paginated(false)
            ->recordActions([
                Action::make('enable')
                    ->label('فعال کردن')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (array $record): bool => ! $record['enabled'])
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => "ماژول {$record['name']} فعال شود؟")
                    ->modalDescription('جدول‌ها و دسترسی‌های این ماژول ساخته می‌شود و پس از آن در منو دیده می‌شود.')
                    ->action(fn (array $record) => $this->setEnabled($record['name'], true)),
                Action::make('disable')
                    ->label('غیرفعال کردن')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (array $record): bool => $record['enabled'])
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => "ماژول {$record['name']} غیرفعال شود؟")
                    ->modalDescription('صفحه‌ها و امکانات این ماژول از دسترس خارج می‌شوند. داده‌ها پاک نمی‌شوند و با فعال کردن دوباره برمی‌گردند.')
                    ->action(fn (array $record) => $this->setEnabled($record['name'], false)),
            ]);
    }

    protected function setEnabled(string $name, bool $enabled): void
    {
        try {
            app(ModuleManager::class)->setEnabled($name, $enabled);
        } catch (ModuleChangeRefused $exception) {
            Notification::make()->title($exception->getMessage())->danger()->persistent()->send();

            return;
        }

        Notification::make()
            ->title($enabled ? "ماژول {$name} فعال شد." : "ماژول {$name} غیرفعال شد.")
            ->success()
            ->send();

        // A full page load: the status decides which providers the next
        // request registers, so the menu only matches after a fresh boot.
        $this->redirect(static::getUrl());
    }
}
