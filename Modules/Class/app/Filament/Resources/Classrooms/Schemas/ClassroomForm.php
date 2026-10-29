<?php

namespace Modules\Class\Filament\Resources\Classrooms\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Term;
use Modules\Class\Support\ClassroomExtensions;

/**
 * A class's details (ADR 0022). Picking a term fills the dates; a
 * sub-class's parent is a top-level class of the same tenant.
 */
class ClassroomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->placeholder('مثلاً: هفتم الف، زبان انگلیسی - مقدماتی ۲، دوره‌ی ایمنی کارگاه')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('کد')
                    ->maxLength(30)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', filament()->getTenant()?->getKey())),
                Select::make('term_id')
                    ->label('دوره‌ی آموزشی')
                    ->options(fn (?Classroom $record): array => Term::options() + ($record?->term ? [$record->term->getKey() => $record->term->title] : []))
                    ->default(fn (): ?int => array_key_first(Term::options()))
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $term = Term::definedTerms()->find($state);

                        $set('starts_on', $term?->starts_on?->toDateString());
                        $set('ends_on', $term?->ends_on?->toDateString());
                    }),
                Select::make('parent_id')
                    ->label('زیرکلاسِ')
                    ->helperText('مثلاً گروه آزمایشگاه یک کلاس؛ اعضای آن از کلاس اصلی می‌آیند.')
                    ->relationship('parent', 'title', fn (Builder $query, ?Classroom $record) => $query->topLevel()->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey())))
                    ->searchable()
                    ->preload()
                    ->disabled(fn (?Classroom $record): bool => $record !== null && $record->children()->exists()),
                ToggleButtons::make('mode')
                    ->label('برگزاری')
                    ->options(ClassMode::class)
                    ->default(ClassMode::InPerson->value)
                    ->inline()
                    ->required()
                    ->live(),
                TextInput::make('capacity')
                    ->label('ظرفیت')
                    ->helperText('تعداد شرکت‌کنندگان؛ خالی یعنی بدون محدودیت.')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10000),
                TextInput::make('location')
                    ->label('مکان')
                    ->placeholder('مثلاً: ساختمان ۲، کلاس ۱۰۴')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => self::mode($get)?->meetsInPerson() ?? true),
                TextInput::make('meeting_url')
                    ->label('لینک کلاس آنلاین')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(500)
                    ->visible(fn (Get $get): bool => self::mode($get)?->meetsOnline() ?? false),
                DatePicker::make('starts_on')
                    ->label('شروع')
                    ->jalali(),
                DatePicker::make('ends_on')
                    ->label('پایان')
                    ->jalali()
                    ->afterOrEqual('starts_on'),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
                // Fields other modules add, e.g. the School module's grade (ADR 0031).
                ...ClassroomExtensions::components('form'),
            ])
            ->columns(2);
    }

    private static function mode(Get $get): ?ClassMode
    {
        $mode = $get('mode');

        return $mode instanceof ClassMode ? $mode : ClassMode::tryFrom((string) $mode);
    }
}
