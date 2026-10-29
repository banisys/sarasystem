<?php

namespace Modules\Class\Filament\Resources\Terms\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->placeholder('مثلاً: سال تحصیلی ۱۴۰۵-۱۴۰۶، ترم پاییز ۱۴۰۵')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                DatePicker::make('starts_on')
                    ->label('شروع')
                    ->jalali()
                    ->required(),
                DatePicker::make('ends_on')
                    ->label('پایان')
                    ->jalali()
                    ->afterOrEqual('starts_on')
                    ->required(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->helperText('کلاس جدید فقط در دوره‌ی فعال ساخته می‌شود.')
                    ->default(true),
            ])
            ->columns(2);
    }
}
