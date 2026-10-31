<?php

namespace Modules\Employee\Filament\Resources\Employees\Pages;

use App\Models\Tenant;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Modules\Employee\Filament\Actions\QuickCreateEmployeeAction;
use Modules\Employee\Filament\Imports\EmployeeImporter;
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Quick entry without the hiring workflow (ADR 0038): one by one, or
     * many from a CSV file, into the current tenant.
     */
    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make('importEmployees')
                ->label('ورود از فایل')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading('ورود کارمندان از فایل')
                ->modalDescription('فایل CSV با ستون‌های نمونه (در Excel: «ذخیره به‌عنوان» ← CSV UTF-8). هر ردیف یک کارمند این '.config('sarasystem.tenant_label').' می‌شود؛ کسی که با همان کد ملی حساب دارد، همان حساب را نگه می‌دارد.')
                ->importer(EmployeeImporter::class)
                ->options(fn (): array => ['tenant_id' => Tenant::current()->getKey()])
                ->visible(fn (): bool => EmployeeResource::canCreate()),
            QuickCreateEmployeeAction::make(),
        ];
    }
}
