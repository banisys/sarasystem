<?php

namespace Modules\Student\Filament\Resources\Programs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Student\Models\Preregistration;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->helperText('مثلاً «پایه هفتم»، «کارشناسی کامپیوتر»، «زبان انگلیسی - مقدماتی» یا «دوره‌ی بدو خدمت».')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->helperText('زیر گزینه‌ی انتخاب‌شده در فرم پیش‌ثبت‌نام نمایش داده می‌شود.')
                    ->rows(3),
                Select::make('workflow_id')
                    ->label('فرایند پذیرش')
                    ->helperText('هر پیش‌ثبت‌نام تازه برای این برنامه از مرحله‌های این فرایند می‌گذرد؛ بدون فرایند، فقط ثبت می‌شود.')
                    ->relationship('workflow', 'name', fn (Builder $query) => $query->for((new Preregistration)->getMorphClass()))
                    ->placeholder('بدون فرایند'),
                Toggle::make('is_active')
                    ->label('نمایش در فرم پیش‌ثبت‌نام')
                    ->default(true),
            ])
            ->columns(1);
    }
}
