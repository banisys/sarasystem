<?php

namespace Modules\Document\Filament\Resources\Documents\Tables;

use App\Documents\DocumentHolders;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Filament\Actions\DocumentActions;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['documentType', 'documentable', 'uploadedBy', 'verifiedBy', 'media']))
            ->defaultSort('uploaded_at', 'desc')
            ->columns(self::columns(withPerson: true))
            ->filters([
                SelectFilter::make('document_type_id')
                    ->label('نوع مدرک')
                    ->options(fn (): array => DocumentType::definedTypes()->ordered()->pluck('title', 'id')->all()),
                SelectFilter::make('holder')
                    ->label('برای')
                    ->options(fn (): array => DocumentHolders::options()),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(DocumentStatus::class),
                Filter::make('expiry')
                    ->schema([
                        Select::make('value')->label('انقضا')->options([
                            'expired' => 'منقضی‌شده',
                            'expiring' => 'تا '.Document::EXPIRING_WITHIN_DAYS.' روز دیگر منقضی می‌شود',
                        ]),
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'expired' => $query->whereDate('expires_at', '<', today()),
                        'expiring' => $query->whereDate('expires_at', '>=', today())->whereDate('expires_at', '<=', today()->addDays(Document::EXPIRING_WITHIN_DAYS)),
                        default => $query,
                    }),
            ])
            ->recordActions(self::recordActions());
    }

    /**
     * The columns, also used in the «مدارک» tab of a person's page (without
     * the person).
     *
     * @return list<TextColumn>
     */
    public static function columns(bool $withPerson): array
    {
        return [
            ...($withPerson ? [
                TextColumn::make('person')
                    ->label('شخص')
                    ->state(fn (Document $record): string => $record->personName()),
                TextColumn::make('holder')
                    ->label('برای')
                    ->formatStateUsing(fn (string $state): string => DocumentHolders::find($state)?->label() ?? $state),
            ] : []),
            TextColumn::make('documentType.title')
                ->label('نوع مدرک'),
            TextColumn::make('status')
                ->label('وضعیت')
                ->badge()
                ->description(fn (Document $record): ?string => $record->status === DocumentStatus::Rejected ? $record->rejection_reason : null),
            TextColumn::make('expires_at')
                ->label('انقضا')
                ->jalaliDate()
                ->placeholder('—')
                ->color(fn (Document $record): ?string => $record->isExpired() ? 'danger' : ($record->isExpiring() ? 'warning' : null))
                ->sortable(),
            TextColumn::make('uploadedBy.name')
                ->label('بارگذاری توسط')
                ->placeholder('خود متقاضی'),
            TextColumn::make('uploaded_at')
                ->label('تاریخ بارگذاری')
                ->jalaliDateTime()
                ->sortable(),
            TextColumn::make('verifiedBy.name')
                ->label('بررسی توسط')
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return list<Action|DeleteAction>
     */
    public static function recordActions(): array
    {
        return [
            DocumentActions::files(),
            DocumentActions::approve(),
            DocumentActions::reject(),
            DocumentActions::replace(),
            DeleteAction::make(),
        ];
    }
}
