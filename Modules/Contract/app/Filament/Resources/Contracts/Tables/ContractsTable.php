<?php

namespace Modules\Contract\Filament\Resources\Contracts\Tables;

use App\Contract\ContractParties;
use App\Filament\Support\TextFilter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Contract\Enums\ContractKind;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Filament\Actions\ContractActions;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['contractType', 'contractable', 'media', 'parent', 'renewals']))
            ->defaultSort('id', 'desc')
            ->columns(self::columns(withPerson: true))
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
                SelectFilter::make('kind')
                    ->label('نوع')
                    ->options(ContractKind::class),
                Filter::make('ending')
                    ->schema([
                        Select::make('value')->label('پایان')->options([
                            'ending' => 'تا '.self::expiringWithinDays().' روز دیگر تمام می‌شود',
                            'ended' => 'تمام شده',
                        ]),
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'ending' => $query->whereDate('ends_on', '>=', today())->whereDate('ends_on', '<=', today()->addDays(self::expiringWithinDays())),
                        'ended' => $query->whereDate('ends_on', '<', today()),
                        default => $query,
                    }),
            ])
            ->recordActions(self::recordActions());
    }

    /**
     * The columns, also used in the «قراردادها» tab of a person's page
     * (without the person).
     *
     * @return list<TextColumn>
     */
    public static function columns(bool $withPerson): array
    {
        return array_values(array_filter([
            TextColumn::make('number')
                ->label('شماره')
                ->placeholder('—')
                ->sortable(),
            TextColumn::make('kind')
                ->label('نوع')
                ->badge()
                ->description(fn (Contract $record): ?string => $record->parent?->number === null ? null : "از {$record->parent->number}"),
            $withPerson ? TextColumn::make('person')
                ->label('شخص')
                ->state(fn (Contract $record): string => $record->personName()) : null,
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
                ->color(fn (Contract $record): ?string => match (true) {
                    $record->ends_on === null => null,
                    $record->ends_on->isPast() && ! $record->ends_on->isToday() => 'gray',
                    $record->canBeRenewed() && $record->ends_on->lessThanOrEqualTo(today()->addDays(self::expiringWithinDays())) => 'warning',
                    default => null,
                })
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
        ]));
    }

    /**
     * @return list<Action|DeleteAction>
     */
    public static function recordActions(): array
    {
        return [
            ContractActions::preview(),
            ContractActions::pdf(),
            ContractActions::edit(),
            ContractActions::issue(),
            ContractActions::recordPaperSignature(),
            ContractActions::amend(),
            ContractActions::renew(),
            ContractActions::scan(),
            ContractActions::cancel(),
            DeleteAction::make()
                ->visible(fn (Contract $record): bool => $record->isDraft()),
        ];
    }

    private static function expiringWithinDays(): int
    {
        return (int) config('contract.reminders.expiring_within_days');
    }
}
