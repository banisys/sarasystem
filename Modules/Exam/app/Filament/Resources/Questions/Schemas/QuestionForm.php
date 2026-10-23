<?php

namespace Modules\Exam\Filament\Resources\Questions\Schemas;

use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Models\QuestionBank;

class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('سؤال')
                    ->columns(2)
                    ->schema([
                        Select::make('banks')
                            ->label('بانک‌ها')
                            ->relationship('banks', 'title')
                            ->multiple()
                            ->required()
                            ->rules([fn (): Closure => self::banksOfCurrentTenant()]),
                        Select::make('type')
                            ->label('نوع')
                            ->options(QuestionType::class)
                            ->default(QuestionType::Choice)
                            ->required()
                            ->live(),
                        Textarea::make('body')
                            ->label('متن سؤال')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('score')
                            ->label('بارم')
                            ->numeric()
                            ->minValue(0.25)
                            ->maxValue(1000)
                            ->default(1)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('فعال')
                            ->helperText('سؤال غیرفعال در آزمون‌های بعدی نمی‌آید.')
                            ->default(true),
                    ]),
                Section::make('گزینه‌ها')
                    ->visible(fn (Get $get): bool => self::isChoice($get('type')))
                    ->schema([
                        Repeater::make('options')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('key')
                                    ->default(fn (): string => Str::lower(Str::random(8))),
                                TextInput::make('text')
                                    ->label('متن گزینه')
                                    ->required()
                                    ->maxLength(1000)
                                    ->columnSpan(3),
                                Toggle::make('is_correct')
                                    ->label('پاسخ درست')
                                    ->inline(false),
                            ])
                            ->columns(4)
                            ->defaultItems(4)
                            ->minItems(2)
                            ->maxItems(10)
                            ->addActionLabel('افزودن گزینه')
                            ->rules([fn (): Closure => self::exactlyOneCorrect()]),
                    ]),
            ]);
    }

    private static function isChoice(mixed $type): bool
    {
        return ($type instanceof QuestionType ? $type : QuestionType::tryFrom((string) $type)) === QuestionType::Choice;
    }

    private static function banksOfCurrentTenant(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $ids = array_unique(array_map('intval', (array) $value));

            if (QuestionBank::query()->whereKey($ids)->count() !== count($ids)) {
                $fail('بانک انتخاب‌شده معتبر نیست.');
            }
        };
    }

    private static function exactlyOneCorrect(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (collect((array) $value)->filter(fn (array $option): bool => (bool) ($option['is_correct'] ?? false))->count() !== 1) {
                $fail('دقیقاً یک گزینه باید پاسخ درست باشد.');
            }
        };
    }
}
