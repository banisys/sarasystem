<?php

namespace Modules\Student\Filament\Resources\Students\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Student\Filament\Resources\Students\StudentResource;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;
}
