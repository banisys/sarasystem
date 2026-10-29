<?php

namespace Modules\Contract\Filament\Resources\Contracts\Pages;

use App\Contract\ContractParties;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Contract\Filament\Actions\ContractActions;
use Modules\Contract\Filament\Resources\Contracts\ContractResource;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Services\Contracts;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->prepareAction(),
        ];
    }

    /**
     * A draft for a member of this tenant; it is issued from its row.
     * Applicants' contracts are prepared in their workflow.
     */
    private function prepareAction(): Action
    {
        return Action::make('prepare')
            ->label('قرارداد جدید')
            ->icon(Heroicon::OutlinedPlus)
            ->authorize('create', Contract::class)
            ->modalWidth('3xl')
            ->schema([
                Select::make('party')
                    ->label('برای')
                    ->options(fn (): array => ContractParties::options())
                    ->required()
                    ->live(),
                Select::make('member')
                    ->label('شخص')
                    ->helperText('با نام یا کد ملی جست‌وجو کنید.')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => ContractParties::find($get('party'))?->searchMembers($search) ?? [])
                    ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => ($member = $this->member($get('party'), $value)) ? ContractParties::find($get('party'))?->personName($member) : null)
                    ->visible(fn (Get $get): bool => filled($get('party')))
                    ->required(),
                Select::make('contract_type_id')
                    ->label('نوع قرارداد')
                    ->options(fn (Get $get): array => filled($get('party')) ? ContractType::definedTypes()->active()->forParty($get('party'))->ordered()->pluck('title', 'id')->all() : [])
                    ->visible(fn (Get $get): bool => filled($get('party')))
                    ->required()
                    ->live(),
                Group::make(fn (Get $get): array => ContractActions::termsFields(ContractType::definedTypes()->find($get('contract_type_id'))))
                    ->columns(2)
                    ->visible(fn (Get $get): bool => filled($get('contract_type_id'))),
            ])
            ->action(function (array $data, Contracts $contracts): void {
                $member = $this->member($data['party'], $data['member']) ?? throw ValidationException::withMessages(['mountedActions.0.data.member' => 'این شخص پیدا نشد.']);
                $type = ContractType::definedTypes()->active()->forParty($data['party'])->findOrFail($data['contract_type_id']);

                /** @var User $user */
                $user = auth()->user();
                ContractActions::attempt(fn () => $contracts->saveDraft(null, $type, $member, $data, $user), 'پیش‌نویس قرارداد ساخته شد؛ برای صدور از ردیف آن «صدور» را بزنید.');
            });
    }

    /**
     * A member of this tenant; the member class's tenant scope keeps it to
     * this tenant's records.
     */
    private function member(?string $partyKey, mixed $id): ?Model
    {
        $party = ContractParties::find($partyKey);

        return $party === null || blank($id) ? null : $party->memberModel()::query()->find($id);
    }
}
