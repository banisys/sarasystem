<?php

namespace Modules\Contract\Filament\Resources\ContractTypes\Tables;

use App\Contract\ContractParties;
use App\Filament\CustomFields\CustomizeFormAction;
use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Contract\Forms\ContractForm;
use Modules\Contract\Models\ContractType;

class ContractTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('contracts'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->sortable(),
                TextColumn::make('parties')
                    ->label('برای')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ContractParties::find($state)?->label() ?? $state),
                TextColumn::make('signature_method')
                    ->label('روش امضا'),
                TextColumn::make('number_prefix')
                    ->label('پیشوند شماره')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                TextColumn::make('contracts_count')
                    ->label('تعداد قرارداد'),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                TernaryFilter::make('is_active')->label('فعال'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('5xl'),
                // Dates, amount and the type's own fields (ADR 0017).
                CustomizeFormAction::make('customizeForm', new ContractForm, perRecord: true)
                    ->label('فیلدهای قرارداد')
                    ->authorize('update'),
                DeleteAction::make()
                    ->visible(fn (ContractType $record): bool => $record->contracts_count === 0)
                    ->modalDescription('نوع قراردادی که قراردادی با آن ساخته شده حذف نمی‌شود؛ آن را غیرفعال کنید.'),
            ]);
    }
}
