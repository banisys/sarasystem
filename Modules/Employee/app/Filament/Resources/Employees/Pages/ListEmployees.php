<?php

namespace Modules\Employee\Filament\Resources\Employees\Pages;

use App\Filament\Actions\SpreadsheetImportAction;
use App\Filament\Support\PersonListActions;
use App\Filament\Support\PersonRecordActions;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Modules\Employee\Filament\Actions\QuickCreateEmployeeAction;
use Modules\Employee\Filament\Imports\EmployeeImporter;
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;
use Modules\Employee\Support\EmployeeImportTemplate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Quick entry without the hiring workflow (ADR 0038): one by one, or
     * many from an Excel or CSV file (ADR 0063), into the current tenant.
     */
    protected function getHeaderActions(): array
    {
        return [
            ...PersonListActions::for(PersonRecordActions::STAFF),
            SpreadsheetImportAction::make('importEmployees')
                ->label('ورود از فایل')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading('ورود کارمندان از فایل')
                ->registerModalActions([
                    Action::make('downloadTemplate')
                        ->label('دریافت فایل نمونه‌ی Excel')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->link()
                        ->action(fn (): StreamedResponse => EmployeeImportTemplate::download(auth()->user(), Tenant::current())),
                ])
                ->modalDescription(fn (SpreadsheetImportAction $action): HtmlString => new HtmlString(
                    e('فایل نمونه را دریافت کنید، اطلاعات کارمندان را مطابق ردیف‌های نمونه در آن بنویسید و نقش هر نفر را از فهرست انتخاب کنید، سپس همان فایل را اینجا بارگذاری کنید (فایل CSV هم پذیرفته می‌شود). هر ردیف یک کارمند این '.config('sarasystem.tenant_label').' می‌شود؛ کسی که با همان کد ملی حساب دارد، همان حساب را نگه می‌دارد.')
                    .'<div class="mt-2">'.$action->getModalAction('downloadTemplate')->toHtml().'</div>'
                ))
                ->importer(EmployeeImporter::class)
                ->options(fn (): array => ['tenant_id' => Tenant::current()->getKey()])
                ->visible(fn (): bool => EmployeeResource::canCreate()),
            QuickCreateEmployeeAction::make(),
        ];
    }
}
