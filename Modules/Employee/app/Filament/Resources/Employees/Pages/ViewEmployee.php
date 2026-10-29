<?php

namespace Modules\Employee\Filament\Resources\Employees\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;
use Modules\Employee\Models\Employee;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string
    {
        /** @var Employee $employee */
        $employee = $this->getRecord();

        return $employee->user?->name ?? 'کارمند';
    }

    /**
     * The details are the first tab, before the tabs other modules add.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'اطلاعات';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
