<?php

namespace Modules\Exam\Filament\Resources\Exams\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Exam\Filament\Resources\Exams\ExamResource;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;
}
