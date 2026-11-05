<?php

namespace Modules\Document\Filament\Resources\DocumentTypes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Document\Enums\FileKind;
use Modules\Document\Models\DocumentType;

/**
 * Reusable document metadata; who uploads and checks follows the Document
 * permissions and workflow steps, form requests are configured separately.
 */
class DocumentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->placeholder('مثلاً: کارت ملی، شناسنامه، آخرین مدرک تحصیلی، عکس پرسنلی')
                    ->required()
                    ->maxLength(255)
                    ->autofocus()
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->helperText('راهنمای بارگذاری؛ مثلاً «پشت و روی کارت».')
                    ->rows(2)
                    ->columnSpanFull(),
                Select::make('file_kind')
                    ->label('نوع فایل')
                    ->options(FileKind::class)
                    ->default(FileKind::ImageOrPdf->value)
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('max_files')
                    ->label('حداکثر تعداد فایل')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10)
                    ->default(1)
                    ->required(),
                TextInput::make('max_kilobytes')
                    ->label('حداکثر حجم هر فایل')
                    ->hint('۵۱۲۰ = ۵ مگابایت')
                    ->suffix('کیلوبایت')
                    ->numeric()
                    ->minValue(100)
                    ->maxValue(20480)
                    ->default(5120)
                    ->required(),
                Toggle::make('has_expiry')
                    ->label('تاریخ انقضا دارد')
                    ->helperText('مثلاً گواهی سلامت؛ مدرک منقضی‌شده مثل مدرک نداشته حساب می‌شود.')
                    ->inline(false),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true)
                    ->inline(false),
            ])
            ->columns(2);
    }

    /** @param array<string, mixed> $data */
    public static function save(?DocumentType $type, array $data): DocumentType
    {
        $type ??= new DocumentType;
        $type->fill($data)->save();

        return $type;
    }
}
