<?php

namespace Modules\School\Filament\Resources\Enrollments\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\School\Filament\Resources\Enrollments\EnrollmentResource;
use Modules\School\Filament\Resources\Enrollments\Schemas\EnrollmentForm;

class ListEnrollments extends ListRecords
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data) => EnrollmentForm::save(null, $data)),
        ];
    }
}
