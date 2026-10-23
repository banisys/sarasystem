<?php

namespace Modules\Exam\Filament\Resources\Questions;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Exam\Filament\Resources\Questions\Pages\CreateQuestion;
use Modules\Exam\Filament\Resources\Questions\Pages\EditQuestion;
use Modules\Exam\Filament\Resources\Questions\Pages\ListQuestions;
use Modules\Exam\Filament\Resources\Questions\Schemas\QuestionForm;
use Modules\Exam\Filament\Resources\Questions\Tables\QuestionsTable;
use Modules\Exam\Models\Question;
use UnitEnum;

/**
 * Questions of the current tenant's banks (ADR 0013).
 */
class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    // See QuestionBankResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'آزمون';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'questions';

    protected static ?string $modelLabel = 'سؤال';

    protected static ?string $pluralModelLabel = 'سؤال‌ها';

    public static function form(Schema $schema): Schema
    {
        return QuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuestions::route('/'),
            'create' => CreateQuestion::route('/create'),
            'edit' => EditQuestion::route('/{record}/edit'),
        ];
    }
}
