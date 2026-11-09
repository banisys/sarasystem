<?php

namespace Modules\Class\Filament\Resources\OnlineServers\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Class\Models\OnlineServer;

class OnlineServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['rooms' => fn ($rooms) => $rooms->withoutGlobalScopes()]))
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان'),
                TextColumn::make('platform')
                    ->label('بستر')
                    ->badge(),
                TextColumn::make('host')
                    ->label('نشانی')
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('rooms_count')
                    ->label('اتاق کلاس‌ها'),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('check')
                    ->label('آزمایش اتصال')
                    ->icon(Heroicon::OutlinedSignal)
                    ->action(function (OnlineServer $record): void {
                        $problem = $record->platform->driver()->problemWith($record);

                        $problem === null
                            ? Notification::make()->title('اتصال به سرور برقرار است.')->success()->send()
                            : Notification::make()->title('اتصال برقرار نشد')->body($problem)->danger()->persistent()->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (OnlineServer $record): bool => $record->rooms_count === 0)
                    ->modalDescription('سروری که کلاسی از آن استفاده کرده حذف نمی‌شود؛ آن را غیرفعال کنید.'),
            ]);
    }
}
