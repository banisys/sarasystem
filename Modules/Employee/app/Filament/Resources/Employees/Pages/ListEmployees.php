<?php

namespace Modules\Employee\Filament\Resources\Employees\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;
}
