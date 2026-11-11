<?php

namespace Modules\Exam\Filament\Resources\QuestionCategories;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Filament\Resources\QuestionCategories\Pages\ListQuestionCategories;
use Modules\Exam\Models\QuestionCategory;
use Modules\Exam\Support\QuestionCategoryTree;
use UnitEnum;

class QuestionCategoryResource extends Resource
{
    protected static ?string $model = QuestionCategory::class;

    // BelongsToTenant provides the tenant scope and fills the tenant on create.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = 'آزمون';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'question-categories';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'دستهٔ سؤال';

    protected static ?string $pluralModelLabel = 'دسته‌های سؤال';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('عنوان')->required()->maxLength(255),
            Select::make('parent_id')
                ->label('دستهٔ والد')
                ->placeholder('بدون والد')
                ->searchable()
                ->options(fn (?QuestionCategory $record): array => QuestionCategoryTree::current()->options($record?->id)),
        ]);
    }

    /** @param array{title: string, parent_id?: int|string|null} $data */
    public static function saveCategory(Action $action, array $data, ?QuestionCategory $record = null): QuestionCategory
    {
        $record ??= new QuestionCategory;

        try {
            $record->fill($data)->save();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                "mountedActions.{$action->getNestingIndex()}.data.parent_id" => $exception->errors()['parent_id'],
            ]);
        }

        return $record;
    }

    public static function getPages(): array
    {
        return ['index' => ListQuestionCategories::route('/')];
    }
}
