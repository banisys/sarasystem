<?php

namespace Modules\Exam\Filament\Resources\Questions\Schemas;

use App\Filament\Forms\Components\TreeSelect;
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
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Support\QuestionCategoryTree;

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
                        TreeSelect::make('category_id')
                            ->label('دستهٔ سؤال')
                            ->placeholder('بدون دسته')
                            ->nodes(fn (): array => QuestionCategoryTree::current()->nodes())
                            ->columnSpanFull(),
                        Select::make('tags')
                            ->label('برچسب‌ها')
                            ->multiple()
                            ->relationship('tags', 'title')
                            ->searchable()
                            ->pivotData(fn (Question $record): array => ['tenant_id' => $record->tenant_id])
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label('متن سؤال')
                            ->helperText(fn (Get $get): ?string => self::type($get('type')) === QuestionType::FillInTheBlank
                                ? 'جای پاسخ را با سه زیرخط (___) مشخص کنید.'
                                : null)
                            ->rule(fn (): Closure => self::exactlyOneBlank(), fn (Get $get): bool => self::type($get('type')) === QuestionType::FillInTheBlank)
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('model_answer')
                            ->label('پاسخ نمونه')
                            ->helperText('اختیاری است و به مصحح در نمره‌دهی کمک می‌کند.')
                            ->visible(fn (Get $get): bool => self::type($get('type')) === QuestionType::Descriptive)
                            ->maxLength(20000)
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
                    ->visible(fn (Get $get): bool => self::type($get('type')) === QuestionType::Choice)
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
                Section::make('پاسخ‌های پذیرفتنی')
                    ->visible(fn (Get $get): bool => self::type($get('type')) === QuestionType::FillInTheBlank)
                    ->schema([
                        Repeater::make('accepted_answers')
                            ->hiddenLabel()
                            ->simple(TextInput::make('answer')->label('پاسخ')->required()->maxLength(255))
                            ->defaultItems(1)
                            ->minItems(1)
                            ->maxItems(10)
                            ->addActionLabel('افزودن پاسخ پذیرفتنی'),
                    ]),
            ]);
    }

    private static function type(mixed $type): ?QuestionType
    {
        return $type instanceof QuestionType ? $type : QuestionType::tryFrom((string) $type);
    }

    private static function exactlyOneBlank(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $parts = explode(Question::BLANK_MARKER, (string) $value);

            if (count($parts) !== 2 || str_ends_with($parts[0], '_') || str_starts_with($parts[1], '_') || blank(implode($parts))) {
                $fail('در متن سؤال دقیقاً یک جای خالی با سه زیرخط (___) مشخص کنید.');
            }
        };
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
