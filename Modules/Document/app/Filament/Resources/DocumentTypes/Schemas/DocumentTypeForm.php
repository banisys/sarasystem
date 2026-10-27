<?php

namespace Modules\Document\Filament\Resources\DocumentTypes\Schemas;

use App\Documents\DocumentHolders;
use App\Models\Role;
use App\Models\Tenant;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Modules\Document\Enums\FileKind;
use Modules\Document\Enums\RequiredFor;
use Modules\Document\Models\DocumentType;
use Modules\Document\Models\DocumentTypeRequirement;

/**
 * A document type with who has it, who uploads and checks it, and for whom
 * it is required (ADR 0019). The requirement owners of each holder (e.g.
 * job positions) are kept in `document_type_requirements`.
 */
class DocumentTypeForm
{
    public const OWNERS = 'requirement_owners';

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
                        CheckboxList::make('holders')
                            ->label('برای')
                            ->options(fn (): array => DocumentHolders::options())
                            ->required()
                            ->columns(2)
                            ->live(),
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
                        Toggle::make('applicant_uploads')
                            ->label('خود متقاضی هم در فرم عمومی بارگذاری کند')
                            ->helperText('در فرم «همکاری با ما» یا «پیش‌ثبت‌نام» پرسیده می‌شود؛ بعد از آن کارمند مسئول فقط بررسی می‌کند.')
                            ->columnSpanFull(),
                    ]),
                Section::make('لازم بودن')
                    ->schema([
                        ToggleButtons::make('required_for')
                            ->hiddenLabel()
                            ->options(RequiredFor::class)
                            ->default(RequiredFor::Nobody->value)
                            ->inline()
                            ->required()
                            ->live(),
                        ...collect(DocumentHolders::all())->map(fn ($holder) => Select::make(self::OWNERS.'.'.$holder->key())
                            ->label("{$holder->requirementOwnerLabel()} ({$holder->label()})")
                            ->helperText("این مدرک برای این‌ها لازم است؛ برای بقیه‌ی {$holder->label()}‌ها اختیاری.")
                            ->multiple()
                            ->options(fn (): array => ($primary = Tenant::primary()) ? $holder->requirementOwnerOptions($primary) : [])
                            ->visible(fn (Get $get): bool => self::requiredFor($get) === RequiredFor::Selected && in_array($holder->key(), (array) $get('holders'), true)))
                            ->values()
                            ->all(),
                    ]),
            ])
            ->columns(1);
    }

    /**
     * The form's state for a type.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fill(DocumentType $type, array $data): array
    {
        return [
            ...$data,
            self::OWNERS => $type->requirements->groupBy('holder')->map(fn ($rows) => $rows->pluck('owner_id')->all())->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(?DocumentType $type, array $data): DocumentType
    {
        $owners = (array) ($data[self::OWNERS] ?? []);
        unset($data[self::OWNERS]);

        $data['upload_role_ids'] = array_map('intval', (array) ($data['upload_role_ids'] ?? []));
        $data['verify_role_ids'] = array_map('intval', (array) ($data['verify_role_ids'] ?? []));

        return DB::transaction(function () use ($type, $data, $owners): DocumentType {
            $type ??= new DocumentType;
            $type->fill($data)->save();

            $type->requirements()->delete();

            if ($type->required_for === RequiredFor::Selected) {
                foreach ($owners as $holder => $ids) {
                    if (! in_array($holder, $type->holders, true)) {
                        continue;
                    }

                    foreach (array_unique(array_map('intval', (array) $ids)) as $id) {
                        DocumentTypeRequirement::query()->create(['document_type_id' => $type->getKey(), 'holder' => $holder, 'owner_id' => $id]);
                    }
                }
            }

            return $type;
        });
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

    private static function requiredFor(Get $get): ?RequiredFor
    {
        $value = $get('required_for');

        return $value instanceof RequiredFor ? $value : RequiredFor::tryFrom((string) $value);
    }
}
