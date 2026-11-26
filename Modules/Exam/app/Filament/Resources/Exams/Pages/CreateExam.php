<?php

namespace Modules\Exam\Filament\Resources\Exams\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Modules\Exam\Filament\Resources\Exams\ExamResource;
use Modules\Exam\Filament\Resources\Exams\Schemas\ExamForm;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    public function form(Schema $schema): Schema
    {
        return ExamForm::configure($schema, forStandaloneCreation: true);
    }
}
