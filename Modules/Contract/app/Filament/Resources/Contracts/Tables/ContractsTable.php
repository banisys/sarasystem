<?php

namespace Modules\Contract\Filament\Resources\Contracts\Tables;

use App\Contract\ContractParties;
use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Filament\Actions\ContractActions;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['contractType', 'contractable', 'media']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('شماره')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('person')
                    ->label('شخص')
                    ->state(fn (Contract $record): string => $record->personName()),
                TextColumn::make('contractType.title')
                    ->label('نوع قرارداد'),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->description(fn (Contract $record): ?string => $record->status === ContractStatus::Cancelled ? $record->cancellation_reason : $record->signed_via?->getLabel()),
                TextColumn::make('starts_on')
                    ->label('شروع')
                    ->jalaliDate()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('پایان')
                    ->jalaliDate()
                    ->placeholder('—')
                    ->color(fn (Contract $record): ?string => $record->ends_on?->isPast() ? 'gray' : null)
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('مبلغ (ریال)')
                    ->numeric()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('signed_at')
                    ->label('تاریخ امضا')
                    ->jalaliDateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TextFilter::make('number')->label('شماره'),
                SelectFilter::make('contract_type_id')
                    ->label('نوع قرارداد')
                    ->options(fn (): array => ContractType::definedTypes()->ordered()->pluck('title', 'id')->all()),
                SelectFilter::make('party')
                    ->label('برای')
                    ->options(fn (): array => ContractParties::options()),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(ContractStatus::class),
            ])
            ->recordActions([
                ContractActions::preview(),
                ContractActions::pdf(),
                ContractActions::edit(),
                ContractActions::issue(),
                ContractActions::recordPaperSignature(),
                ContractActions::scan(),
                ContractActions::cancel(),
                DeleteAction::make()
                    ->visible(fn (Contract $record): bool => $record->isDraft()),
            ]);
    }
}
