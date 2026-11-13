<?php

namespace Modules\Exam\Filament\Resources\Questions\Tables;

use App\Filament\Forms\Components\TreeSelect;
use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
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
            ->modifyQueryUsing(fn ($query) => $query->with(['banks', 'tags']))
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
                    ->state(function (Question $record) use ($categories): ?Htmlable {
                        $path = $categories()->path($record->category_id);

                        return $path === null ? null : view('filament.components.hierarchy-paths', ['paths' => [$path]]);
                    })
                    ->placeholder('بدون دسته')
                    ->wrap(),
                TextColumn::make('tags.title')
                    ->label('برچسب‌ها')
                    ->badge()
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
                    ->multiple()
                    ->resetState(['values' => []])
                    ->indicateUsing(function (array $data) use ($categories): array {
                        $paths = [];
                        foreach ($data['values'] ?? [] as $value) {
                            $path = match (true) {
                                $value === 'uncategorized' => ['بدون دسته'],
                                filter_var($value, FILTER_VALIDATE_INT) !== false => $categories()->path((int) $value),
                                default => null,
                            };

                            if ($path !== null) {
                                $paths[] = $path;
                            }
                        }

                        return $paths === [] ? [] : [
                            Indicator::make(view('filament.components.hierarchy-paths', [
                                'label' => 'دسته و زیردسته‌ها',
                                'paths' => $paths,
                            ])),
                        ];
                    })
                    ->schema([
                        TreeSelect::make('values')
                            ->label('دسته و زیردسته‌ها')
                            ->placeholder('همهٔ دسته‌ها')
                            ->multiple()
                            ->nodes(fn (): array => [
                                ['id' => 'uncategorized', 'label' => 'بدون دسته'],
                                ...$categories()->nodes(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data) use ($categories): Builder {
                        $values = $data['values'] ?? [];
                        if ($values === []) {
                            return $query;
                        }

                        $ids = [];
                        foreach ((array) $values as $value) {
                            $id = filter_var($value, FILTER_VALIDATE_INT);
                            if ($id !== false) {
                                $ids += array_fill_keys($categories()->branchIds($id), true);
                            }
                        }

                        return $query->where(fn (Builder $branches): Builder => $branches
                            ->whereIn('category_id', array_keys($ids))
                            ->when(in_array('uncategorized', (array) $values, true), fn (Builder $branches): Builder => $branches->orWhereNull('category_id')));
                    }),
                SelectFilter::make('tags')
                    ->label('برچسب‌ها')
                    ->multiple()
                    ->relationship('tags', 'title')
                    ->searchable(),
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
