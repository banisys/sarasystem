<?php

namespace Modules\Employee\Filament\Imports;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\IranianMobile;
use App\Rules\JalaliDate;
use App\Rules\NationalCode;
use App\Support\Identifiers;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Models\Employee;
use Modules\Employee\Services\EmployeeException;
use Modules\Employee\Services\RegisterEmployee;
use Modules\Employee\Support\EmployeeRoles;

/**
 * Employees of one tenant from a CSV file (ADR 0038), one RegisterEmployee
 * call per row, on the queue. The tenant comes from the action's options;
 * the importing user must be allowed to create employees there. Roles are
 * the ones chosen in the import modal, unless a row names its own (labels
 * separated by «،» or «,»). Failed rows come back as a CSV with the reason.
 */
class EmployeeImporter extends Importer
{
    protected static ?string $model = Employee::class;

    public static function getColumns(): array
    {
        return [
            static::column('name', 'نام و نام خانوادگی', 'زهرا احمدی')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            static::column('national_code', 'کد ملی', '0012345679')
                ->requiredMapping()
                ->rules(['required', new NationalCode]),
            static::column('mobile', 'موبایل', '09121234567')
                ->requiredMapping()
                ->rules(['required', new IranianMobile]),
            static::column('job_title', 'سمت', 'معلم ریاضی')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            static::column('personnel_code', 'کد پرسنلی', '1024')
                ->rules(['nullable', 'string', 'max:20']),
            static::column('employment_type', 'نوع همکاری', EmploymentType::FullTime->getLabel())
                ->castStateUsing(fn (?string $state): ?string => static::employmentType($state)?->value ?? $state)
                ->rules(['nullable', 'in:'.implode(',', array_column(EmploymentType::cases(), 'value'))]),
            static::column('hired_at', 'تاریخ شروع به کار', '1404/07/01')
                ->rules(['nullable', new JalaliDate]),
            static::column('roles', 'نقش‌ها', 'معلم')
                ->helperText('خالی: نقش‌های انتخاب‌شده در پایین. چند نقش را با «،» جدا کنید.'),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            CheckboxList::make('role_ids')
                ->label('نقش‌ها در این '.config('sarasystem.tenant_label'))
                ->helperText('برای ردیف‌هایی که ستون «نقش‌ها» را خالی گذاشته‌اند.')
                ->options(fn (): array => EmployeeRoles::options(auth()->user(), Tenant::current()))
                ->columns(3),
            Toggle::make('panel_access')
                ->label('دسترسی به پنل مدیریت برای همه'),
        ];
    }

    /**
     * Always a new employee: rows never update existing records.
     */
    public function resolveRecord(): Employee
    {
        return new Employee;
    }

    public function getValidationMessages(): array
    {
        return [
            'employment_type.in' => 'نوع همکاری باید یکی از این‌ها باشد: '.collect(EmploymentType::cases())->map->getLabel()->join('، ').'.',
        ];
    }

    /**
     * RegisterEmployee does the saving; the columns fill nothing themselves.
     */
    public function fillRecord(): void {}

    public function saveRecord(): void
    {
        /** @var User $actor */
        $actor = $this->import->user;
        $tenant = Tenant::query()->find($this->options['tenant_id'] ?? null);

        if ($tenant === null || ! $tenant->execute(fn (): bool => $actor->unsetRelation('roles')->unsetRelation('permissions')->can('create', Employee::class))) {
            throw new RowImportFailedException('اجازه‌ی ثبت کارمند در این '.config('sarasystem.tenant_label').' را ندارید.');
        }

        $roleIds = filled($this->data['roles'] ?? null)
            ? $this->rowRoleIds($actor, $tenant, (string) $this->data['roles'])
            : array_map('intval', $this->options['role_ids'] ?? []);
        $panelAccess = (bool) ($this->options['panel_access'] ?? false);

        if ($roleIds === [] && ! $panelAccess) {
            throw new RowImportFailedException('نقشی برای این ردیف مشخص نیست.');
        }

        try {
            $this->record = app(RegisterEmployee::class)->register(
                $actor,
                $tenant,
                person: [
                    'name' => (string) $this->data['name'],
                    'national_code' => (string) $this->data['national_code'],
                    'mobile' => (string) $this->data['mobile'],
                ],
                employment: [
                    'job_title' => (string) $this->data['job_title'],
                    'personnel_code' => $this->data['personnel_code'] ?? null,
                    'employment_type' => filled($this->data['employment_type'] ?? null) ? $this->data['employment_type'] : EmploymentType::FullTime,
                    'hired_at' => JalaliDate::toDate($this->data['hired_at'] ?? null) ?? today(),
                ],
                roleIds: $roleIds,
                panelAccess: $panelAccess,
            );
        } catch (EmployeeException $exception) {
            throw new RowImportFailedException($exception->getMessage());
        }
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return 'ورود کارمندان تمام شد';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = Number::format($import->successful_rows).' کارمند ثبت شد.';

        if ($failed = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failed).' ردیف ثبت نشد؛ فایل آن‌ها را با علت خطا دریافت کنید.';
        }

        return $body;
    }

    private static function column(string $name, string $label, string $example): ImportColumn
    {
        return ImportColumn::make($name)
            ->label($label)
            ->exampleHeader($label)
            ->example($example)
            ->castStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null);
    }

    /**
     * An employment type from its label («تمام‌وقت») or value («full_time»).
     */
    private static function employmentType(?string $state): ?EmploymentType
    {
        $state = trim((string) $state);

        return collect(EmploymentType::cases())
            ->first(fn (EmploymentType $type): bool => $type->value === $state || $type->getLabel() === $state);
    }

    /**
     * Ids of the roles a row names by label or name, all of which the actor
     * may give in the tenant.
     *
     * @return list<int>
     */
    private function rowRoleIds(User $actor, Tenant $tenant, string $value): array
    {
        /** @var Collection<int, Role> $assignable */
        $assignable = EmployeeRoles::assignable($actor, $tenant);

        return collect(preg_split('/[,،;]/u', Identifiers::toLatinDigits($value)))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->map(fn (string $name): int => $assignable->first(fn (Role $role): bool => $role->display_name === $name || $role->name === $name)?->getKey()
                ?? throw new RowImportFailedException("نقش «{$name}» پیدا نشد یا اجازه‌ی دادنش را ندارید."))
            ->unique()
            ->values()
            ->all();
    }
}
