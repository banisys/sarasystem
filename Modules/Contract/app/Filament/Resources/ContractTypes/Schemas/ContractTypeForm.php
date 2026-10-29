<?php

namespace Modules\Contract\Filament\Resources\ContractTypes\Schemas;

use App\Contract\ContractParties;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Contract\Enums\SignatureMethod;
use Modules\Contract\Models\ContractType;

/**
 * A contract type: for whom, how it is signed, and its text with merge
 * tags (ADR 0020). Its fields are set up with «فیلدهای قرارداد» in the
 * list (ADR 0017); added fields then appear as merge tags too.
 */
class ContractTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('قرارداد')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('عنوان')
                            ->placeholder('مثلاً: قرارداد کارمند تمام‌وقت، قرارداد شهریه')
                            ->required()
                            ->maxLength(255),
                        CheckboxList::make('parties')
                            ->label('برای')
                            ->options(fn (): array => ContractParties::options())
                            ->required()
                            ->columns(2)
                            ->live(),
                        ToggleButtons::make('signature_method')
                            ->label('روش امضا')
                            ->options(SignatureMethod::class)
                            ->default(SignatureMethod::Electronic->value)
                            ->helperText('الکترونیکی: شخص در پنل خودش متن را می‌خواند و با کد پیامکی امضا می‌کند. کاغذی: نسخه‌ی چاپی امضا و تصویرش بارگذاری می‌شود.')
                            ->inline()
                            ->required(),
                        Toggle::make('is_amendment')
                            ->label('الحاقیه است')
                            ->helperText('برای افزودن به قرارداد امضاشده (مثلاً افزایش حقوق)؛ شماره‌اش از شماره‌ی قرارداد اصلی می‌آید و برای قرارداد جدید یا تمدید پیشنهاد نمی‌شود.')
                            ->live(),
                        TextInput::make('number_prefix')
                            ->label('پیشوند شماره')
                            ->helperText('مثلاً «ک-۱۴۰۵-»؛ شماره‌ها به ترتیب بعد از آن می‌آیند.')
                            ->visible(fn (Get $get): bool => ! $get('is_amendment'))
                            ->maxLength(20),
                        Textarea::make('description')
                            ->label('توضیحات')
                            ->rows(2)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('فعال')
                            ->default(true),
                    ]),
                Section::make('متن قرارداد')
                    ->description('برای درج اطلاعات شخص و قرارداد، «{{» تایپ کنید یا از دکمه‌ی برچسب‌ها در نوار ابزار استفاده کنید. فیلدهایی که با «فیلدهای قرارداد» اضافه می‌کنید هم این‌جا می‌آیند. متن هر قرارداد هنگام صدور ثابت می‌شود.')
                    ->schema([
                        RichEditor::make('body')
                            ->hiddenLabel()
                            ->mergeTags(fn (Get $get, ?ContractType $record): array => ContractType::tagsFor(array_values((array) $get('parties')), $record))
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline'],
                                ['h2', 'h3'],
                                ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                                ['bulletList', 'orderedList'],
                                ['table', 'mergeTags'],
                                ['undo', 'redo'],
                            ])
                            ->minHeight('24rem')
                            ->required(),
                    ]),
            ])
            ->columns(1);
    }
}
