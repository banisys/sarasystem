<?php

namespace Modules\Contract\Filament\RelationManagers;

use App\Contract\ContractParties;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Contract\Filament\Actions\ContractActions;
use Modules\Contract\Filament\Resources\Contracts\Tables\ContractsTable;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Services\Contracts;

/**
 * The «قراردادها» tab of an employee's or learner's page (ADR 0020): their
 * contracts with the same actions as the contracts list, and a new one for
 * them. Added to each party's member model by the module's provider.
 */
class ContractsRelationManager extends RelationManager
{
    public const RELATIONSHIP = 'contracts';

    protected static string $relationship = self::RELATIONSHIP;

    protected static ?string $title = 'قراردادها';

    protected static ?string $modelLabel = 'قرارداد';

    protected static ?string $pluralModelLabel = 'قراردادها';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['contractType', 'contractable', 'media', 'parent', 'renewals']))
            ->defaultSort('id', 'desc')
            ->columns(ContractsTable::columns(withPerson: false))
            ->headerActions([$this->newContractAction()])
            ->recordActions(ContractsTable::recordActions());
    }

    /**
     * A draft for this person; it is issued from its row.
     */
    private function newContractAction(): Action
    {
        return Action::make('prepare')
            ->label('قرارداد جدید')
            ->icon(Heroicon::OutlinedPlus)
            ->authorize('create', Contract::class)
            ->modalWidth('3xl')
            ->schema([
                Select::make('contract_type_id')
                    ->label('نوع قرارداد')
                    ->options(fn (): array => $this->typeOptions())
                    ->required()
                    ->live(),
                Group::make(fn (Get $get): array => ContractActions::termsFields(ContractType::definedTypes()->find($get('contract_type_id'))))
                    ->columns(2)
                    ->visible(fn (Get $get): bool => filled($get('contract_type_id'))),
            ])
            ->action(function (array $data, Contracts $contracts): void {
                $type = ContractType::definedTypes()->active()->amendments(false)->findOrFail($data['contract_type_id']);

                /** @var User $user */
                $user = auth()->user();
                ContractActions::attempt(fn () => $contracts->saveDraft(null, $type, $this->getOwnerRecord(), $data, $user), 'پیش‌نویس قرارداد ساخته شد؛ برای صدور از ردیف آن «صدور» را بزنید.');
            });
    }

    /**
     * @return array<int, string>
     */
    private function typeOptions(): array
    {
        $party = ContractParties::forRecord($this->getOwnerRecord());

        return $party === null ? [] : ContractType::definedTypes()->active()->amendments(false)->forParty($party->key())->ordered()->pluck('title', 'id')->all();
    }
}
