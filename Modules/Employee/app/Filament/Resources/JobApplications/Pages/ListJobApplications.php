<?php

namespace Modules\Employee\Filament\Resources\JobApplications\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Employee\Filament\Resources\JobApplications\JobApplicationResource;

class ListJobApplications extends ListRecords
{
    protected static string $resource = JobApplicationResource::class;
}
