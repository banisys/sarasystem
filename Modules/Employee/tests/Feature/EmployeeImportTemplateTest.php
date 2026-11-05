<?php

namespace Modules\Employee\Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Modules\Employee\Filament\Imports\EmployeeImporter;
use Modules\Employee\Filament\Resources\Employees\Pages\ListEmployees;
use Modules\Employee\Models\Employee;
use Modules\Employee\Support\EmployeeImportTemplate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The Excel file of «ورود از فایل»: downloading it and bringing it back (ADR 0063).
 */
class EmployeeImportTemplateTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private User $manager;

    private Role $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->west = Tenant::factory()->create();

        $this->roleWithPermissions('hr', ['ViewAny:Employee', 'View:Employee', 'Create:Employee', 'Update:Employee']);
        $this->teacher = Role::findOrCreate('teacher', 'web');
        $this->teacher->update(['label' => 'معلم']);
        Role::findOrCreate('janitor', 'web')->update(['label' => 'خدمتگزار، سرایدار']);
        $this->roleWithPermissions('principal', ['ViewAny:Employee', 'Delete:Employee']);

        $this->manager = User::factory()->create();
        $this->makeStaff($this->manager, $this->west);
        $this->grantRole($this->manager, $this->west, 'hr');
    }

    public function test_the_template_has_sample_rows_and_a_list_of_the_roles_the_downloader_may_give(): void
    {
        $spreadsheet = $this->reloaded(EmployeeImportTemplate::spreadsheet($this->manager, $this->west));
        $employees = $spreadsheet->getSheetByName(EmployeeImportTemplate::SHEET_EMPLOYEES);
        $lists = $spreadsheet->getSheetByName(EmployeeImportTemplate::SHEET_LISTS);

        $this->assertSame(0, $spreadsheet->getIndex($employees));
        $this->assertSame('نام و نام خانوادگی', $employees->getCell('A1')->getValue());
        $this->assertSame('نقش‌ها', $employees->getCell('H1')->getValue());
        $this->assertSame('زهرا احمدی', $employees->getCell('A2')->getValue());
        $this->assertSame('0012345679', $employees->getCell('B2')->getValue());
        $this->assertSame('0023456787', $employees->getCell('B3')->getValue());

        $roles = collect($lists->toArray())->pluck(0)->filter()->all();
        $this->assertContains('معلم', $roles);
        $this->assertContains('خدمتگزار، سرایدار', $roles);
        $this->assertNotContains('principal', $roles, 'A stronger role is not offered.');
        $this->assertNotContains(Role::SUPER_ADMIN, $roles);
        $this->assertContains($employees->getCell('H2')->getValue(), $roles);

        $validation = $employees->getDataValidationCollection()['H2:H'.EmployeeImportTemplate::LAST_ROW];
        $this->assertSame('list', $validation->getType());
        $this->assertSame('lists!$A$1:$A$'.count($roles), $validation->getFormula1());
        $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $lists->getSheetState());
    }

    public function test_the_import_modal_downloads_the_template(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->callAction([TestAction::make('importEmployees'), TestAction::make('downloadTemplate')])
            ->assertFileDownloaded("employees-{$this->west->slug}.xlsx");
    }

    public function test_a_filled_template_is_imported_with_each_rows_chosen_role(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        $template = EmployeeImportTemplate::spreadsheet($this->manager, $this->west);
        $sheet = $template->getSheet(0);
        $this->fillRow($sheet, 2, 'مریم نوری', '0013542419', 'معلم');
        $this->fillRow($sheet, 3, 'حسن رضوی', '0499370899', 'خدمتگزار، سرایدار');

        Livewire::test(ListEmployees::class)
            ->mountAction('importEmployees')
            ->fillForm(['file' => $this->upload($template)])
            ->assertSchemaStateSet(['columnMap' => [
                'name' => 'نام و نام خانوادگی',
                'national_code' => 'کد ملی',
                'mobile' => 'موبایل',
                'job_title' => 'سمت',
                'personnel_code' => 'کد پرسنلی',
                'employment_type' => 'نوع همکاری',
                'hired_at' => 'تاریخ شروع به کار',
                'roles' => 'نقش‌ها',
            ]])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $import = Import::query()->sole();
        $this->assertSame([2, 2], [$import->total_rows, $import->successful_rows]);

        $maryam = Employee::withoutTenantScope()->with('user')->get()->firstWhere('user.national_code', '0013542419');
        $this->assertSame($this->west->id, $maryam->tenant_id);
        $this->assertSame('مریم نوری', $maryam->user->name);
        $this->assertSame([$this->teacher->id], $maryam->user->roleIdsIn($this->west));

        $hassan = Employee::withoutTenantScope()->with('user')->get()->firstWhere('user.national_code', '0499370899');
        $this->assertSame([Role::findByName('janitor', 'web')->id], $hassan->user->roleIdsIn($this->west));
    }

    public function test_sample_rows_left_in_the_file_are_refused_with_the_reason(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->mountAction('importEmployees')
            ->fillForm(['file' => $this->upload(EmployeeImportTemplate::spreadsheet($this->manager, $this->west))])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $import = Import::query()->sole();
        $this->assertSame([2, 0], [$import->total_rows, $import->successful_rows]);
        $this->assertStringContainsString('نمونه', $import->failedRows()->first()->validation_error);
        $this->assertSame(0, Employee::withoutTenantScope()->count());
    }

    public function test_empty_rows_and_columns_are_ignored_and_formulas_are_not_calculated(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        $spreadsheet = EmployeeImportTemplate::spreadsheet($this->manager, $this->west);
        $sheet = $spreadsheet->getSheet(0);
        $this->fillRow($sheet, 2, 'مریم نوری', '0013542419', 'معلم');
        $this->fillRow($sheet, 3, 'فرمول', '0499370899', 'معلم');
        $sheet->setCellValue('C3', '=1+1');
        $sheet->setCellValueExplicit('C50', ' ', DataType::TYPE_STRING);

        Livewire::test(ListEmployees::class)
            ->mountAction('importEmployees')
            ->fillForm(['file' => $this->upload($spreadsheet)])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $import = Import::query()->sole();
        $this->assertSame([2, 1], [$import->total_rows, $import->successful_rows]);
        $this->assertSame('=1+1', $import->failedRows()->first()->data['موبایل']);
    }

    public function test_the_modal_asks_only_for_the_file(): void
    {
        $this->assertSame([], EmployeeImporter::getOptionsFormComponents());
    }

    public function test_a_csv_file_is_refused(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->mountAction('importEmployees')
            ->fillForm(['file' => UploadedFile::fake()->createWithContent('employees.csv', "نام و نام خانوادگی,کد ملی,موبایل,سمت,نقش‌ها\nزهرا,0013542419,09121112233,معلم,معلم\n")])
            ->callMountedAction()
            ->assertHasFormErrors(['file']);

        $this->assertSame(0, Import::query()->count());
    }

    public function test_a_file_without_a_required_column_is_refused_at_upload(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        $spreadsheet = EmployeeImportTemplate::spreadsheet($this->manager, $this->west);
        $spreadsheet->getSheet(0)->setCellValue('H1', 'ستون دیگر');

        Livewire::test(ListEmployees::class)
            ->mountAction('importEmployees')
            ->fillForm(['file' => $this->upload($spreadsheet)])
            ->callMountedAction()
            ->assertHasFormErrors(['file']);

        $this->assertSame(0, Import::query()->count());
    }

    public function test_a_row_without_a_role_fails_with_a_reason(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        $spreadsheet = EmployeeImportTemplate::spreadsheet($this->manager, $this->west);
        $sheet = $spreadsheet->getSheet(0);
        $this->fillRow($sheet, 2, 'مریم نوری', '0013542419', '');
        $sheet->removeRow(3);

        Livewire::test(ListEmployees::class)
            ->mountAction('importEmployees')
            ->fillForm(['file' => $this->upload($spreadsheet)])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $import = Import::query()->sole();
        $this->assertSame([1, 0], [$import->total_rows, $import->successful_rows]);
        $this->assertSame(0, Employee::withoutTenantScope()->count());
    }

    public function test_a_broken_excel_file_is_refused_at_upload(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->mountAction('importEmployees')
            ->fillForm(['file' => UploadedFile::fake()->createWithContent('employees.xlsx', 'not a spreadsheet')])
            ->callMountedAction()
            ->assertHasFormErrors(['file']);

        $this->assertSame(0, Import::query()->count());
    }

    private function fillRow(Worksheet $sheet, int $row, string $name, string $nationalCode, string $role): void
    {
        $sheet->setCellValueExplicit("A{$row}", $name, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B{$row}", $nationalCode, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("C{$row}", '0912111'.substr($nationalCode, -4), DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("H{$row}", $role, DataType::TYPE_STRING);
    }

    private function upload(Spreadsheet $spreadsheet): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('employees.xlsx', $this->bytes($spreadsheet));
    }

    private function reloaded(Spreadsheet $spreadsheet): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'template');
        file_put_contents($path, $this->bytes($spreadsheet));
        $loaded = IOFactory::createReader(IOFactory::READER_XLSX)->load($path);
        unlink($path);

        return $loaded;
    }

    private function bytes(Spreadsheet $spreadsheet): string
    {
        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');

        return ob_get_clean();
    }
}
