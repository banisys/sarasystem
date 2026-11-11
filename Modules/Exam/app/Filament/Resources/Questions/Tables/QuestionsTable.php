<?php

namespace Modules\Exam\Filament\Resources\Questions\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Models\Question;
use Modules\Exam\Support\QuestionCategoryTree;

class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        $tree = null;
        $categories = function () use (&$tree): QuestionCategoryTree {
            return $tree ??= QuestionCategoryTree::current();
        };

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('banks'))
            ->columns([
                TextColumn::make('body')
                    ->label('متن سؤال')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('type')
                    ->label('نوع')
                    ->badge(),
                TextColumn::make('banks.title')
                    ->label('بانک‌ها')
                    ->badge(),
                TextColumn::make('category_path')
                    ->label('دسته')
                    ->state(fn (Question $record): ?string => $categories()->label($record->category_id))
                    ->placeholder('بدون دسته')
                    ->wrap(),
                TextColumn::make('score')
                    ->label('بارم')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->filters([
                TextFilter::make('body')->label('متن سؤال'),
                SelectFilter::make('bank')
                    ->label('بانک')
                    ->relationship('banks', 'title'),
                SelectFilter::make('category')
                    ->label('دسته و زیردسته‌ها')
                    ->searchable()
                    ->options(fn (): array => ['uncategorized' => 'بدون دسته'] + $categories()->options())
                    ->query(function (Builder $query, array $data) use ($categories): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            null, '' => $query,
                            'uncategorized' => $query->whereNull('category_id'),
                            default => $query->whereIn('category_id', $categories()->branchIds((int) filter_var($value, FILTER_VALIDATE_INT))),
                        };
                    }),
                SelectFilter::make('type')
                    ->label('نوع')
                    ->options(QuestionType::class),
                TernaryFilter::make('is_active')
                    ->label('فعال'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('سؤال از همه‌ی بانک‌ها حذف می‌شود. آزمون‌هایی که قبلاً داده شده‌اند، متن سؤال را نگه می‌دارند.'),
            ]);
    }
}
