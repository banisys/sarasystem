<?php

namespace Modules\Exam\Filament\Resources\Exams;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Exam\Filament\Resources\Exams\Pages\CreateExam;
use Modules\Exam\Filament\Resources\Exams\Pages\EditExam;
use Modules\Exam\Filament\Resources\Exams\Pages\ListExams;
use Modules\Exam\Filament\Resources\Exams\Schemas\ExamForm;
use Modules\Exam\Filament\Resources\Exams\Tables\ExamsTable;
use Modules\Exam\Models\Exam;
use UnitEnum;

/**
 * Exams of the current tenant (ADR 0013): questions drawn from banks,
 * rules, result visibility and participants.
 */
class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    // See QuestionBankResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'آزمون';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'exams';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'آزمون';

    protected static ?string $pluralModelLabel = 'آزمون‌ها';

    public static function form(Schema $schema): Schema
    {
        return ExamForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExams::route('/'),
            'create' => CreateExam::route('/create'),
            'edit' => EditExam::route('/{record}/edit'),
        ];
    }
}
