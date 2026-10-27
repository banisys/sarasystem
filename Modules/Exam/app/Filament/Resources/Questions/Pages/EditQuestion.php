<?php

namespace Modules\Exam\Filament\Resources\Questions\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Exam\Filament\Resources\Questions\QuestionResource;

class EditQuestion extends EditRecord
{
    protected static string $resource = QuestionResource::class;
}
