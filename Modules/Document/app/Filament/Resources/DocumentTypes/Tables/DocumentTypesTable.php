<?php

namespace Modules\Document\Filament\Resources\DocumentTypes\Tables;

use App\Documents\DocumentHolders;
use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Document\Filament\Resources\DocumentTypes\Schemas\DocumentTypeForm;
use Modules\Document\Models\DocumentType;

class DocumentTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('requirements')->withCount('documents'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->sortable(),
                TextColumn::make('holders')
                    ->label('برای')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => DocumentHolders::find($state)?->label() ?? $state),
                TextColumn::make('required_for')
                    ->label('لازم بودن'),
                TextColumn::make('file_kind')
                    ->label('نوع فایل')
                    ->toggleable(),
                IconColumn::make('applicant_uploads')
                    ->label('بارگذاری متقاضی')
                    ->boolean(),
                IconColumn::make('has_expiry')
                    ->label('انقضا')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                TextColumn::make('documents_count')
                    ->label('تعداد مدارک'),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                TernaryFilter::make('is_active')->label('فعال'),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, DocumentType $record): array => DocumentTypeForm::fill($record, $data))
                    ->using(fn (Model $record, array $data): DocumentType => DocumentTypeForm::save($record, $data)),
                DeleteAction::make()
                    ->visible(fn (DocumentType $record): bool => $record->documents_count === 0)
                    ->modalDescription('نوع مدرکی که مدرکی با آن بارگذاری شده حذف نمی‌شود؛ آن را غیرفعال کنید.'),
            ]);
    }
}
