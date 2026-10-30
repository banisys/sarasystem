<?php

namespace Modules\Document\Filament\Resources\DocumentTypes\Schemas;

use App\Models\Role;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Document\Enums\FileKind;
use Modules\Document\Models\DocumentType;

/**
 * Reusable document metadata and staff permissions; form requests are configured separately.
 */
class DocumentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('مدرک')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('عنوان')
                            ->placeholder('مثلاً: کارت ملی، شناسنامه، آخرین مدرک تحصیلی، عکس پرسنلی')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('توضیحات')
                            ->helperText('راهنمای بارگذاری؛ مثلاً «پشت و روی کارت».')
                            ->rows(2)
                            ->columnSpanFull(),
                        Select::make('file_kind')
                            ->label('نوع فایل')
                            ->options(FileKind::class)
                            ->default(FileKind::ImageOrPdf->value)
                            ->required(),
                        TextInput::make('max_files')
                            ->label('حداکثر تعداد فایل')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->default(1)
                            ->required(),
                        TextInput::make('max_kilobytes')
                            ->label('حداکثر حجم هر فایل (کیلوبایت)')
                            ->helperText('۵۱۲۰ کیلوبایت = ۵ مگابایت')
                            ->numeric()
                            ->minValue(100)
                            ->maxValue(20480)
                            ->default(5120)
                            ->required(),
                        Toggle::make('has_expiry')
                            ->label('تاریخ انقضا دارد')
                            ->helperText('مثلاً گواهی سلامت؛ مدرک منقضی‌شده مثل مدرک نداشته حساب می‌شود.'),
                        Toggle::make('is_active')
                            ->label('فعال')
                            ->default(true),
                    ]),
                Section::make('چه کسی بارگذاری و بررسی می‌کند')
                    ->description('در هر '.config('sarasystem.tenant_label').'، دارندگان این نقش‌ها می‌توانند این مدرک را بارگذاری یا تأیید کنند. در فرایندها، مسئول هر مرحله هم کار مدارکی را که برای آن مرحله تعیین شده انجام می‌دهد.')
                    ->columns(2)
                    ->schema([
                        Select::make('upload_role_ids')
                            ->label('نقش‌های مجاز به بارگذاری')
                            ->multiple()
                            ->options(fn (): array => self::roleOptions()),
                        Select::make('verify_role_ids')
                            ->label('نقش‌های مجاز به تأیید یا رد')
                            ->multiple()
                            ->options(fn (): array => self::roleOptions()),
                    ]),
            ])
            ->columns(1);
    }

    /** @param array<string, mixed> $data */
    public static function save(?DocumentType $type, array $data): DocumentType
    {
        $data['upload_role_ids'] = array_map('intval', (array) ($data['upload_role_ids'] ?? []));
        $data['verify_role_ids'] = array_map('intval', (array) ($data['verify_role_ids'] ?? []));
        $type ??= new DocumentType;
        $type->fill($data)->save();

        return $type;
    }

    /**
     * Global roles, except super admin and panel access.
     *
     * @return array<int, string>
     */
    private static function roleOptions(): array
    {
        return Role::query()
            ->whereNull('tenant_id')
            ->whereNotIn('name', [Role::SUPER_ADMIN, Utils::getPanelUserRoleName()])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [$role->getKey() => $role->display_name])
            ->all();
    }
}
