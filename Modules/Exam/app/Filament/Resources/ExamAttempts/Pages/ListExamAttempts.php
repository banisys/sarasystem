<?php

namespace Modules\Exam\Filament\Resources\ExamAttempts\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Exam\Filament\Resources\ExamAttempts\ExamAttemptResource;
use Modules\Exam\Services\ExamTaking;

class ListExamAttempts extends ListRecords
{
    protected static string $resource = ExamAttemptResource::class;

    /**
     * Attempts left open past their time (the participant closed the page)
     * are closed with their saved answers before they are listed.
     */
    public function mount(): void
    {
        app(ExamTaking::class)->closeOverdueAttempts();

        parent::mount();
    }
}
