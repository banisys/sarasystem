<?php

namespace Modules\Employee\Filament\Resources\JobApplications\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Employee\Filament\Actions\DownloadResumeAction;
use Modules\Employee\Filament\Resources\JobApplications\JobApplicationResource;
use Modules\Employee\Models\JobApplication;

class ViewJobApplication extends ViewRecord
{
    protected static string $resource = JobApplicationResource::class;

    public function getTitle(): string
    {
        /** @var JobApplication $application */
        $application = $this->getRecord();

        return "درخواست همکاری {$application->fullName()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            DownloadResumeAction::make(),
        ];
    }
}
