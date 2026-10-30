<?php

namespace Modules\Document\Filament\Resources\DocumentTypes\Tables;

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
            ->modifyQueryUsing(fn ($query) => $query->withCount('documents'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->sortable(),
                TextColumn::make('file_kind')
                    ->label('نوع فایل')
                    ->toggleable(),
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
                    ->using(fn (Model $record, array $data): DocumentType => DocumentTypeForm::save($record, $data)),
                DeleteAction::make()
                    ->visible(fn (DocumentType $record): bool => $record->documents_count === 0)
                    ->modalDescription('نوع مدرکی که مدرکی با آن بارگذاری شده حذف نمی‌شود؛ آن را غیرفعال کنید.'),
            ]);
    }
}
