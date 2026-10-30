<?php

namespace Modules\Document\Filament\Resources\Documents\Pages;

use App\Documents\DocumentHolders;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use Modules\Document\Filament\Actions\DocumentActions;
use Modules\Document\Filament\Resources\Documents\DocumentResource;
use Modules\Document\Models\DocumentType;
use Modules\Document\Services\Documents;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->uploadAction(),
        ];
    }

    /**
     * Upload a document for an applicant or member of this tenant, of a
     * type the user may upload here.
     */
    private function uploadAction(): Action
    {
        return Action::make('upload')
            ->label('بارگذاری مدرک')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->visible(fn (): bool => DocumentActions::uploadableTypes(null) !== [])
            ->schema([
                Select::make('holder')
                    ->label('برای')
                    ->options(fn (): array => DocumentHolders::options())
                    ->required()
                    ->live(),
                Select::make('record')
                    ->label('شخص')
                    ->helperText('با نام یا کد ملی جست‌وجو کنید.')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => DocumentHolders::find($get('holder'))?->search($search) ?? [])
                    ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => ($record = $this->resolvePerson($get('holder'), $value)) ? DocumentHolders::find($get('holder'))?->personName($record) : null)
                    ->visible(fn (Get $get): bool => filled($get('holder')))
                    ->required(),
                Select::make('document_type_id')
                    ->label('نوع مدرک')
                    ->options(fn (Get $get): array => DocumentActions::uploadableTypes($get('holder')))
                    ->visible(fn (Get $get): bool => filled($get('holder')))
                    ->required()
                    ->live(),
                Group::make(fn (Get $get): array => DocumentActions::uploadFields(DocumentType::definedTypes()->find($get('document_type_id'))))
                    ->visible(fn (Get $get): bool => filled($get('document_type_id'))),
            ])
            ->action(function (array $data, Documents $documents): void {
                $record = $this->resolvePerson($data['holder'], $data['record']) ?? throw ValidationException::withMessages(['mountedActions.0.data.record' => 'این شخص پیدا نشد.']);
                $type = DocumentType::definedTypes()->active()->findOrFail($data['document_type_id']);

                abort_unless(DocumentActions::canUpload($type), 403);

                /** @var User $user */
                $user = auth()->user();
                $documents->upload($record, $type, (array) $data['files'], DocumentActions::expiry($data), $user);

                Notification::make()->title('مدرک بارگذاری شد')->success()->send();
            });
    }

    /**
     * An applicant or member record of this tenant, from a `type:id` key.
     */
    private function resolvePerson(?string $holderKey, mixed $key): ?Model
    {
        $holder = DocumentHolders::find($holderKey);

        if ($holder === null || ! is_string($key) || ! str_contains($key, ':')) {
            return null;
        }

        [$type, $id] = explode(':', $key, 2);
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! in_array($class, [$holder->applicantModel(), $holder->memberModel()], true)) {
            return null;
        }

        // The class's tenant scope keeps it to this tenant's records.
        $record = $class::query()->find($id);
        $tenant = Filament::getTenant();

        return $record !== null && $tenant instanceof Tenant && (int) $record->getAttribute('tenant_id') === $tenant->getKey() ? $record : null;
    }
}
