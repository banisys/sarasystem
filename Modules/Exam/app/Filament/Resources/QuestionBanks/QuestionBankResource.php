<?php

namespace Modules\Exam\Filament\Resources\QuestionBanks;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Exam\Filament\Resources\QuestionBanks\Pages\ListQuestionBanks;
use Modules\Exam\Filament\Resources\QuestionBanks\Schemas\QuestionBankForm;
use Modules\Exam\Filament\Resources\QuestionBanks\Tables\QuestionBanksTable;
use Modules\Exam\Models\QuestionBank;
use UnitEnum;

/**
 * Question banks of the current tenant (ADR 0013); created and renamed in
 * a modal, questions are added in «سؤال‌ها».
 */
class QuestionBankResource extends Resource
{
    protected static ?string $model = QuestionBank::class;

    // BelongsToTenant's scope limits the list and new rows get the current
    // tenant; Filament's tenant scoping would need a relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'آزمون';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'question-banks';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'بانک سؤال';

    protected static ?string $pluralModelLabel = 'بانک‌های سؤال';

    public static function form(Schema $schema): Schema
    {
        return QuestionBankForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionBanksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuestionBanks::route('/'),
        ];
    }
}
