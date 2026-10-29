<?php

namespace Modules\Class\Filament\Resources\TeachingPlans\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Class\Models\Subject;
use Modules\Class\Models\TeachingPlan;

/**
 * A plan and its topics in order; the order is the order sessions get them.
 */
class TeachingPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->placeholder('مثلاً: ریاضی هفتم، English Elementary - Book 1')
                    ->required()
                    ->maxLength(255),
                Select::make('subject_id')
                    ->label('درس')
                    ->helperText('در برنامه‌ی همین درس پیشنهاد می‌شود؛ خالی یعنی برای همه.')
                    ->options(fn (?TeachingPlan $record): array => Subject::options($record?->subject_id))
                    ->searchable(),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
                Repeater::make('topics')
                    ->label('سرفصل‌ها')
                    ->relationship()
                    ->orderColumn('sort')
                    ->addActionLabel('افزودن سرفصل')
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->collapsible()
                    ->columns(6)
                    ->schema([
                        TextInput::make('title')
                            ->label('سرفصل')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(5),
                        TextInput::make('sessions')
                            ->label('تعداد جلسه')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(1)
                            ->required(),
                        Textarea::make('description')
                            ->label('توضیح')
                            ->rows(1)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
