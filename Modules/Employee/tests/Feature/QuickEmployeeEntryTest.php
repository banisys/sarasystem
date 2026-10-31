<?php

namespace Modules\Employee\Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestableImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Modules\Employee\Database\Factories\JobApplicationFactory;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Filament\Imports\EmployeeImporter;
use Modules\Employee\Filament\Resources\Employees\Pages\ListEmployees;
use Modules\Employee\Models\Employee;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * «ثبت سریع کارمند» and «ورود از فایل» (ADR 0038).
 */
class QuickEmployeeEntryTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private Tenant $north;

    private User $manager;

    private Role $teacher;

    private Role $janitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->west = Tenant::factory()->create(['name' => 'غرب']);
        $this->north = Tenant::factory()->create(['name' => 'شمال']);

        $this->roleWithPermissions('hr', ['ViewAny:Employee', 'View:Employee', 'Create:Employee', 'Update:Employee']);
        $this->teacher = Role::findOrCreate('teacher', 'web');
        $this->teacher->update(['label' => 'معلم']);
        $this->janitor = Role::findOrCreate('janitor', 'web');
        $this->janitor->update(['label' => 'خدمتگزار']);
        $this->roleWithPermissions('principal', ['ViewAny:Employee', 'Delete:Employee']);

        $this->manager = User::factory()->create();
        $this->makeStaff($this->manager, $this->west);
        $this->grantRole($this->manager, $this->west, 'hr');
    }

    public function test_quick_entry_creates_a_user_who_signs_in_with_an_sms_code(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->callAction('quickCreate', data: [
                'national_code' => '۰۰۱۳۵۴۲۴۱۹',
                'name' => 'زهرا احمدی',
                'mobile' => '۰۹۱۲۱۱۱۲۲۳۳',
                'job_title' => 'معلم ریاضی',
                'personnel_code' => '1024',
                'employment_type' => EmploymentType::Hourly->value,
                'hired_at' => '2026-09-23',
                'role_ids' => [$this->teacher->id],
                'panel_access' => false,
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('زهرا احمدی ثبت شد.');

        $user = User::query()->where('national_code', '0013542419')->sole();
        $this->assertSame('زهرا احمدی', $user->name);
        $this->assertSame('09121112233', $user->mobile);
        $this->assertNull($user->email);
        $this->assertSame([$this->teacher->id], $user->roleIdsIn($this->west));

        $employee = Employee::withoutTenantScope()->where('user_id', $user->id)->sole();
        $this->assertSame($this->west->id, $employee->tenant_id);
        $this->assertSame('معلم ریاضی', $employee->job_title);
        $this->assertSame('1024', $employee->personnel_code);
        $this->assertSame(EmploymentType::Hourly, $employee->employment_type);
        $this->assertSame(EmploymentStatus::Active, $employee->status);
        $this->assertSame('2026-09-23', $employee->hired_at->toDateString());

        // No password: the new employee signs in with an SMS code.
        $sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $sms);
        auth()->guard()->logout();
        $this->post('/login/code/send', ['identifier' => '09121112233'])->assertSessionHasNoErrors();
        $this->post('/login/code', ['identifier' => '09121112233', 'code' => $sms->lastCodeFor('09121112233')])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_panel_access_adds_the_panel_role(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->callAction('quickCreate', data: $this->formData(['panel_access' => true]))
            ->assertHasNoFormErrors();

        $user = User::query()->where('national_code', '0013542419')->sole();
        $panelRole = Role::query()->whereNull('tenant_id')->where('name', Utils::getPanelUserRoleName())->sole();
        $this->assertEqualsCanonicalizing([$this->teacher->id, $panelRole->id], $user->roleIdsIn($this->west));
    }

    public function test_an_existing_account_is_reused_without_changing_its_identity(): void
    {
        $existing = User::factory()->create(['name' => 'علی رضایی', 'national_code' => '0013542419', 'mobile' => '09350000000']);
        $this->grantRole($existing, $this->north, 'teacher');
        $password = $existing->password;

        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->mountAction('quickCreate')
            ->fillForm(['national_code' => '0013542419'])
            ->assertFormFieldHidden('name')
            ->assertFormFieldHidden('mobile')
            ->callMountedAction()
            ->assertHasFormErrors(['job_title' => 'required'])
            ->fillForm(['job_title' => 'معلم علوم', 'role_ids' => [$this->teacher->id]])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('حساب موجود علی رضایی کارمند این شعبه شد.');

        $existing->refresh();
        $this->assertSame(1, User::query()->where('national_code', '0013542419')->count());
        $this->assertSame(['علی رضایی', '09350000000', $password], [$existing->name, $existing->mobile, $existing->password]);
        $this->assertSame([$this->teacher->id], $existing->roleIdsIn($this->west));
        $this->assertSame([$this->teacher->id], $existing->roleIdsIn($this->north));
        $this->assertTrue(Employee::withoutTenantScope()->where('tenant_id', $this->west->id)->where('user_id', $existing->id)->exists());
    }

    public function test_someone_already_employed_here_is_refused(): void
    {
        $user = User::factory()->create(['national_code' => '0013542419']);
        Employee::factory()->for($this->west)->create(['user_id' => $user->id]);

        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->callAction('quickCreate', data: $this->formData())
            ->assertHasFormErrors(['national_code']);

        $this->assertSame(1, Employee::withoutTenantScope()->count());
    }

    public function test_an_employee_of_another_tenant_can_be_employed_here_too(): void
    {
        $user = User::factory()->create(['national_code' => '0013542419']);
        $this->grantRole($user, $this->north, 'teacher');
        Employee::factory()->for($this->north)->create(['user_id' => $user->id]);

        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->callAction('quickCreate', data: $this->formData())
            ->assertHasNoFormErrors();

        $this->assertSame(2, Employee::withoutTenantScope()->where('user_id', $user->id)->count());
    }

    public function test_a_stronger_existing_user_is_not_taken_over(): void
    {
        $principal = User::factory()->create(['national_code' => '0013542419']);
        $this->grantRole($principal, $this->north, 'principal');

        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->callAction('quickCreate', data: $this->formData())
            ->assertNotified();

        $this->assertSame(0, Employee::withoutTenantScope()->count());
        $this->assertSame([], $principal->roleIdsIn($this->west));
    }

    public function test_roles_the_manager_may_not_give_are_refused(): void
    {
        $this->actingInPanel($this->manager, $this->west);
        $principal = Role::query()->whereNull('tenant_id')->where('name', 'principal')->sole();

        Livewire::test(ListEmployees::class)
            ->callAction('quickCreate', data: $this->formData(['role_ids' => [$principal->id]]))
            ->assertHasFormErrors(['role_ids']);

        $this->assertSame(0, Employee::withoutTenantScope()->count());
    }

    public function test_a_taken_personnel_code_is_refused(): void
    {
        Employee::factory()->for($this->north)->create(['personnel_code' => '1024']);

        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->callAction('quickCreate', data: $this->formData(['personnel_code' => '1024']))
            ->assertHasFormErrors(['personnel_code']);
    }

    public function test_save_and_next_keeps_the_job_details_and_roles(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListEmployees::class)
            ->callAction('quickCreate', data: $this->formData(), arguments: ['another' => true])
            ->assertHasNoFormErrors()
            ->assertActionMounted('quickCreate')
            ->assertSchemaStateSet([
                'national_code' => null,
                'name' => null,
                'job_title' => 'معلم ریاضی',
                'role_ids' => [$this->teacher->id],
            ]);

        $this->assertSame(1, Employee::withoutTenantScope()->count());
    }

    public function test_quick_entry_and_import_need_the_create_permission(): void
    {
        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $this->west);
        $this->roleWithPermissions('viewer', ['ViewAny:Employee']);
        $this->grantRole($viewer, $this->west, 'viewer');

        $this->actingInPanel($viewer, $this->west);

        Livewire::test(ListEmployees::class)
            ->assertActionHidden('quickCreate')
            ->assertActionHidden('importEmployees');
    }

    public function test_employees_are_created_in_the_current_tenant_only(): void
    {
        $this->makeStaff($this->manager, $this->north);
        $this->grantRole($this->manager, $this->north, 'hr');

        $this->actingInPanel($this->manager, $this->west);
        Livewire::test(ListEmployees::class)->callAction('quickCreate', data: $this->formData())->assertHasNoFormErrors();
        $employee = Employee::withoutTenantScope()->sole();

        $this->actingInPanel($this->manager, $this->north);
        Livewire::test(ListEmployees::class)->assertCanNotSeeTableRecords([$employee]);
        $this->assertSame($this->west->id, $employee->tenant_id);
        $this->assertSame([], $employee->user->roleIdsIn($this->north));
    }

    public function test_import_creates_employees_from_rows(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        $this->importer(['role_ids' => [$this->teacher->id]])
            ->import($this->row([
                'national_code' => '۰۰۱۳۵۴۲۴۱۹',
                'employment_type' => 'پاره‌وقت',
                'hired_at' => '۱۴۰۴/۰۷/۰۱',
                'personnel_code' => '۷۷',
            ]))
            ->assertImported();

        $employee = Employee::withoutTenantScope()->with('user')->sole();
        $this->assertSame($this->west->id, $employee->tenant_id);
        $this->assertSame('0013542419', $employee->user->national_code);
        $this->assertSame('09121112233', $employee->user->mobile);
        $this->assertSame(EmploymentType::PartTime, $employee->employment_type);
        $this->assertSame('2025-09-23', $employee->hired_at->toDateString());
        $this->assertSame('77', $employee->personnel_code);
        $this->assertSame([$this->teacher->id], $employee->user->roleIdsIn($this->west));
    }

    public function test_import_defaults_employment_type_and_start_date(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        $this->importer(['role_ids' => [$this->teacher->id]])->import($this->row())->assertImported();

        $employee = Employee::withoutTenantScope()->sole();
        $this->assertSame(EmploymentType::FullTime, $employee->employment_type);
        $this->assertSame(today()->toDateString(), $employee->hired_at->toDateString());
    }

    public function test_a_rows_own_roles_replace_the_chosen_ones(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        $this->importer(['role_ids' => [$this->teacher->id]])
            ->import($this->row(['roles' => 'خدمتگزار، teacher']))
            ->assertImported();

        $this->assertEqualsCanonicalizing(
            [$this->janitor->id, $this->teacher->id],
            User::query()->where('national_code', '0013542419')->sole()->roleIdsIn($this->west),
        );
    }

    public function test_import_rows_fail_with_a_reason(): void
    {
        Employee::factory()->for($this->north)->create(['personnel_code' => '1024']);
        $this->actingInPanel($this->manager, $this->west);
        $importer = $this->importer(['role_ids' => [$this->teacher->id]]);

        $importer->import($this->row(['roles' => 'principal']))->assertHasRowFailure('نقش «principal» پیدا نشد یا اجازه‌ی دادنش را ندارید.');
        $importer->import($this->row(['personnel_code' => '1024']))->assertHasRowFailure('این کد پرسنلی قبلاً به کارمند دیگری داده شده است.');
        $this->assertTrue($importer->import($this->row(['national_code' => '1234567890']))->errors()->has('national_code'));
        $this->assertTrue($importer->import($this->row(['hired_at' => '1404/13/01']))->errors()->has('hired_at'));
        $this->assertTrue($importer->import($this->row(['employment_type' => 'نیمه‌وقت']))->errors()->has('employment_type'));
        $this->assertTrue($importer->import($this->row(['job_title' => '']))->errors()->has('job_title'));

        $this->importer()->import($this->row())->assertHasRowFailure('نقشی برای این ردیف مشخص نیست.');

        $this->assertSame(1, Employee::withoutTenantScope()->count());
        $this->assertSame(0, User::query()->where('national_code', '0013542419')->count());
    }

    public function test_import_reuses_an_existing_account_once(): void
    {
        $existing = User::factory()->create(['name' => 'علی رضایی', 'national_code' => '0013542419']);
        $this->actingInPanel($this->manager, $this->west);
        $importer = $this->importer(['role_ids' => [$this->teacher->id]]);

        $importer->import($this->row(['name' => 'نام دیگر']))->assertImported();
        $importer->import($this->row())->assertHasRowFailure('علی رضایی از قبل کارمند غرب است.');

        $this->assertSame('علی رضایی', $existing->refresh()->name);
        $this->assertSame(1, Employee::withoutTenantScope()->count());
    }

    public function test_import_into_a_tenant_where_the_user_may_not_create_employees_fails(): void
    {
        $this->actingInPanel($this->manager, $this->west);

        $this->importer(['role_ids' => [$this->teacher->id], 'tenant_id' => $this->north->id])
            ->import($this->row())
            ->assertHasRowFailure('اجازه‌ی ثبت کارمند در این شعبه را ندارید.');

        $this->assertSame(0, Employee::withoutTenantScope()->count());
    }

    public function test_a_csv_file_is_imported_from_the_list(): void
    {
        $this->actingInPanel($this->manager, $this->west);
        $second = JobApplicationFactory::nationalCode();
        $csv = "\u{FEFF}نام و نام خانوادگی,کد ملی,موبایل,سمت,نقش‌ها\n"
            ."زهرا احمدی,0013542419,09121112233,معلم ریاضی,\n"
            ."رضا کریمی,{$second},09121112244,سرایدار,خدمتگزار\n"
            ."بدون کد,123,09121112255,معلم,\n";

        Livewire::test(ListEmployees::class)
            ->mountAction('importEmployees')
            ->fillForm(['file' => UploadedFile::fake()->createWithContent('employees.csv', $csv)])
            ->assertSchemaStateSet(['columnMap' => [
                'name' => 'نام و نام خانوادگی',
                'national_code' => 'کد ملی',
                'mobile' => 'موبایل',
                'job_title' => 'سمت',
                'personnel_code' => null,
                'employment_type' => null,
                'hired_at' => null,
                'roles' => 'نقش‌ها',
            ]])
            ->fillForm(['role_ids' => [$this->teacher->id]])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $import = Import::query()->sole();
        $this->assertSame([3, 2], [$import->total_rows, $import->successful_rows]);
        $this->assertSame(1, $import->failedRows()->count());

        $employees = Employee::withoutTenantScope()->with('user')->get()->keyBy('user.national_code');
        $this->assertEqualsCanonicalizing(['0013542419', $second], $employees->keys()->map(fn ($code): string => (string) $code)->all());
        $this->assertSame([$this->teacher->id], $employees['0013542419']->user->roleIdsIn($this->west));
        $this->assertSame([$this->janitor->id], $employees[$second]->user->roleIdsIn($this->west));
        $this->assertSame($this->west->id, $employees[$second]->tenant_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formData(array $overrides = []): array
    {
        return [
            'national_code' => '0013542419',
            'name' => 'زهرا احمدی',
            'mobile' => '09121112233',
            'job_title' => 'معلم ریاضی',
            'employment_type' => EmploymentType::FullTime->value,
            'hired_at' => '2026-09-23',
            'role_ids' => [$this->teacher->id],
            'panel_access' => false,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return [
            'name' => 'زهرا احمدی',
            'national_code' => '0013542419',
            'mobile' => '09121112233',
            'job_title' => 'معلم ریاضی',
            'personnel_code' => '',
            'employment_type' => '',
            'hired_at' => '',
            'roles' => '',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function importer(array $options = []): TestableImport
    {
        $import = new Import;
        $import->user()->associate($this->manager);

        return EmployeeImporter::test(options: ['tenant_id' => $this->west->id, ...$options], import: $import);
    }
}
