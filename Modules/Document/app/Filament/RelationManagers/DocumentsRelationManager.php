<?php

namespace Modules\Document\Filament\RelationManagers;

use App\Documents\DocumentHolders;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Document\Filament\Actions\DocumentActions;
use Modules\Document\Filament\Resources\Documents\Tables\DocumentsTable;
use Modules\Document\Models\DocumentType;
use Modules\Document\Services\Documents;

/**
 * The «مدارک» tab of an employee's or learner's page (ADR 0019): their
 * documents with the same actions as the documents list, and an upload for
 * them. Added to each holder's member model by the module's provider.
 */
class DocumentsRelationManager extends RelationManager
{
    public const RELATIONSHIP = 'documents';

    protected static string $relationship = self::RELATIONSHIP;

    protected static ?string $title = 'مدارک';

    protected static ?string $modelLabel = 'مدرک';

    protected static ?string $pluralModelLabel = 'مدارک';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['documentType', 'documentable', 'uploadedBy', 'verifiedBy', 'media']))
            ->defaultSort('uploaded_at', 'desc')
            ->columns(DocumentsTable::columns(withPerson: false))
            ->headerActions([$this->newDocumentAction()])
            ->recordActions(DocumentsTable::recordActions());
    }

    /**
     * Upload a document of a type the user may upload in this tenant.
     */
    private function newDocumentAction(): Action
    {
        return Action::make('upload')
            ->label('بارگذاری مدرک')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->visible(fn (): bool => DocumentActions::uploadableTypes($this->holderKey()) !== [])
            ->schema([
                Select::make('document_type_id')
                    ->label('نوع مدرک')
                    ->options(fn (): array => DocumentActions::uploadableTypes($this->holderKey()))
                    ->required()
                    ->live(),
                Group::make(fn (Get $get): array => DocumentActions::uploadFields(DocumentType::definedTypes()->find($get('document_type_id'))))
                    ->visible(fn (Get $get): bool => filled($get('document_type_id'))),
            ])
            ->action(function (array $data, Documents $documents): void {
                $type = DocumentType::definedTypes()->active()->findOrFail($data['document_type_id']);

                abort_unless(DocumentActions::canUpload($type), 403);

                /** @var User $user */
                $user = auth()->user();
                $documents->upload($this->getOwnerRecord(), $type, (array) $data['files'], DocumentActions::expiry($data), $user);

                Notification::make()->title('مدرک بارگذاری شد')->success()->send();
            });
    }

    private function holderKey(): ?string
    {
        return DocumentHolders::forRecord($this->getOwnerRecord())?->key();
    }
}
