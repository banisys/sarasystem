<?php

namespace Modules\Employee\Filament\Resources\JobPositions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Employee\Models\JobApplication;

class JobPositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->helperText('زیر گزینه‌ی انتخاب‌شده در فرم همکاری با ما نمایش داده می‌شود.')
                    ->rows(3),
                Select::make('workflow_id')
                    ->label('فرایند استخدام')
                    ->helperText('هر درخواست تازه برای این موقعیت از مرحله‌های این فرایند می‌گذرد؛ بدون فرایند، درخواست فقط ثبت می‌شود.')
                    ->relationship('workflow', 'name', fn (Builder $query) => $query->for((new JobApplication)->getMorphClass()))
                    ->placeholder('بدون فرایند'),
                Toggle::make('is_active')
                    ->label('نمایش در فرم همکاری با ما')
                    ->default(true),
            ])
            ->columns(1);
    }
}
