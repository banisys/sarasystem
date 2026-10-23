<?php

namespace Modules\Exam\Filament\Resources\ExamAttempts;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Exam\Filament\Resources\ExamAttempts\Pages\GradeExamAttempt;
use Modules\Exam\Filament\Resources\ExamAttempts\Pages\ListExamAttempts;
use Modules\Exam\Filament\Resources\ExamAttempts\Schemas\GradeAttemptForm;
use Modules\Exam\Filament\Resources\ExamAttempts\Tables\ExamAttemptsTable;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use UnitEnum;

/**
 * Attempts at the current tenant's exams (ADR 0013): the results list, and
 * a page per attempt to see its answers and grade written ones
 * (Update:ExamAttempt).
 */
class ExamAttemptResource extends Resource
{
    protected static ?string $model = ExamAttempt::class;

    // See QuestionBankResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'آزمون';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'exam-results';

    protected static ?string $modelLabel = 'نتیجه‌ی آزمون';

    protected static ?string $pluralModelLabel = 'نتایج آزمون‌ها';

    public static function form(Schema $schema): Schema
    {
        return GradeAttemptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamAttemptsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * The results list filtered to one exam.
     */
    public static function urlForExam(Exam $exam): string
    {
        return static::getUrl('index', ['filters' => ['exam' => ['value' => $exam->getKey()]]]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExamAttempts::route('/'),
            'edit' => GradeExamAttempt::route('/{record}'),
        ];
    }
}
