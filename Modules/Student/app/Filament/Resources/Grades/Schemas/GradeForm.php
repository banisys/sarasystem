<?php

namespace Modules\Student\Filament\Resources\Grades\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Student\Models\Grade;
use Modules\Student\Models\Preregistration;

/**
 * Two levels only: a stage, or a grade of a stage. The workflow and the
 * description are the stage's.
 */
class GradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->required()
                    ->maxLength(255),
                Select::make('parent_id')
                    ->label('مقطع')
                    ->helperText('برای تعریف مقطع خالی بگذارید؛ برای تعریف پایه، مقطع آن را انتخاب کنید.')
                    ->placeholder('— این یک مقطع است —')
                    ->options(fn (?Grade $record): array => Grade::query()
                        ->stages()
                        ->when($record !== null, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                        ->ordered()
                        ->pluck('title', 'id')
                        ->all())
                    // A stage with grades stays a stage.
                    ->disabled(fn (?Grade $record): bool => $record?->children()->exists() ?? false)
                    ->live(),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->helperText('زیر مقطع انتخاب‌شده در فرم پیش‌ثبت‌نام نمایش داده می‌شود.')
                    ->rows(3)
                    ->visible(fn (Get $get): bool => blank($get('parent_id'))),
                Select::make('workflow_id')
                    ->label('فرایند پذیرش')
                    ->helperText('هر پیش‌ثبت‌نام تازه در این مقطع از مرحله‌های این فرایند می‌گذرد؛ بدون فرایند، فقط ثبت می‌شود.')
                    ->relationship('workflow', 'name', fn (Builder $query) => $query->for((new Preregistration)->getMorphClass()))
                    ->placeholder('بدون فرایند')
                    ->visible(fn (Get $get): bool => blank($get('parent_id'))),
                TextInput::make('sort')
                    ->label('ترتیب')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label('نمایش در فرم پیش‌ثبت‌نام')
                    ->default(true),
            ])
            ->columns(1);
    }
}
