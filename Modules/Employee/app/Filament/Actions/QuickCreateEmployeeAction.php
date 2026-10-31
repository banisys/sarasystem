<?php

namespace Modules\Employee\Filament\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Rules\IranianMobile;
use App\Rules\NationalCode;
use App\Support\Identifiers;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Models\Employee;
use Modules\Employee\Services\EmployeeException;
use Modules\Employee\Services\RegisterEmployee;
use Modules\Employee\Support\EmployeeRoles;

/**
 * «ثبت سریع کارمند» (ADR 0038): an employee of the current tenant from the
 * least data that lets them sign in, without the hiring workflow. The rest
 * of their file is completed later. «ثبت و بعدی» keeps the job details and
 * roles for the next person.
 */
class QuickCreateEmployeeAction
{
    public static function make(): CreateAction
    {
        return CreateAction::make('quickCreate')
            ->label('ثبت سریع کارمند')
            ->icon(Heroicon::OutlinedBolt)
            ->modalHeading('ثبت سریع کارمند')
            ->modalDescription('کارمند با موبایل خود و کد پیامکی وارد سامانه می‌شود؛ بقیه‌ی اطلاعات پرونده را بعداً کامل کنید.')
            ->modalSubmitActionLabel('ثبت')
            ->createAnotherAction(fn (Action $action): Action => $action->label('ثبت و بعدی'))
            ->preserveFormDataWhenCreatingAnother(['job_title', 'employment_type', 'hired_at', 'role_ids', 'panel_access'])
            ->schema(fn (Schema $schema): Schema => $schema->components(static::fields())->columns(2))
            ->using(function (array $data, Action $action): Employee {
                try {
                    return app(RegisterEmployee::class)->register(
                        auth()->user(),
                        Tenant::current(),
                        person: [
                            'name' => $data['name'] ?? '',
                            'national_code' => $data['national_code'],
                            'mobile' => $data['mobile'] ?? null,
                        ],
                        employment: [
                            'job_title' => $data['job_title'],
                            'personnel_code' => $data['personnel_code'] ?? null,
                            'employment_type' => $data['employment_type'],
                            'hired_at' => $data['hired_at'],
                        ],
                        roleIds: $data['role_ids'] ?? [],
                        panelAccess: (bool) ($data['panel_access'] ?? false),
                    );
                } catch (EmployeeException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                    $action->halt();
                }
            })
            ->successNotificationTitle(fn (Employee $record): string => $record->user->wasRecentlyCreated
                ? "{$record->user->name} ثبت شد."
                : "حساب موجود {$record->user->name} کارمند این ".config('sarasystem.tenant_label').' شد.');
    }

    /**
     * @return list<mixed>
     */
    private static function fields(): array
    {
        $isNew = fn (Get $get): bool => static::existingUser($get('national_code')) === null;

        return [
            TextInput::make('national_code')
                ->label('کد ملی')
                ->required()
                ->rule(new NationalCode)
                ->rule(fn (): Closure => static::notAlreadyEmployeeRule())
                ->live(onBlur: true)
                ->helperText(fn (?string $state): ?string => ($user = static::existingUser($state)) !== null
                    ? "کاربری با این کد ملی هست ({$user->name})؛ همان حساب کارمند می‌شود و نام و موبایلش تغییر نمی‌کند."
                    : null)
                ->extraInputAttributes(['dir' => 'ltr']),
            TextInput::make('name')
                ->label('نام و نام خانوادگی')
                ->required($isNew)
                ->visible($isNew)
                ->maxLength(255),
            TextInput::make('mobile')
                ->label('موبایل')
                ->required($isNew)
                ->visible($isNew)
                ->rule(new IranianMobile)
                // Not ->tel(): its pattern refuses Persian digits, which IranianMobile accepts.
                ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'tel']),
            TextInput::make('job_title')
                ->label('سمت')
                ->required()
                ->maxLength(255),
            TextInput::make('personnel_code')
                ->label('کد پرسنلی')
                ->maxLength(20)
                ->rule(Rule::unique((new Employee)->getTable(), 'personnel_code')),
            Select::make('employment_type')
                ->label('نوع همکاری')
                ->options(EmploymentType::class)
                ->default(EmploymentType::FullTime->value)
                ->required(),
            DatePicker::make('hired_at')
                ->label('تاریخ شروع به کار')
                ->jalali()
                ->default(now())
                ->required(),
            CheckboxList::make('role_ids')
                ->label('نقش‌ها در این '.config('sarasystem.tenant_label'))
                ->helperText('فقط نقش‌هایی که خودتان اجازه‌ی دادنشان را دارید.')
                ->options(fn (): array => EmployeeRoles::options(auth()->user(), Tenant::current()))
                ->rule(fn (): Closure => static::onlyOfferedRolesRule())
                ->required()
                ->columns(3)
                ->columnSpanFull(),
            Toggle::make('panel_access')
                ->label('دسترسی به پنل مدیریت')
                ->default(false)
                ->columnSpanFull(),
        ];
    }

    private static function existingUser(?string $nationalCode): ?User
    {
        $nationalCode = Identifiers::toLatinDigits(trim((string) $nationalCode));

        return Identifiers::isValidNationalCode($nationalCode)
            ? User::query()->where('national_code', $nationalCode)->first()
            : null;
    }

    /**
     * CheckboxList doesn't check submitted ids against its options.
     */
    private static function onlyOfferedRolesRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $offered = array_keys(EmployeeRoles::options(auth()->user(), Tenant::current()));

            if (collect($value ?? [])->map(fn ($id): int => (int) $id)->diff($offered)->isNotEmpty()) {
                $fail('شما اجازه‌ی دادن یکی از نقش‌های انتخاب‌شده را ندارید.');
            }
        };
    }

    private static function notAlreadyEmployeeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $user = static::existingUser((string) $value);

            if ($user !== null && Employee::query()->where('user_id', $user->getKey())->exists()) {
                $fail("{$user->name} از قبل کارمند این ".config('sarasystem.tenant_label').' است.');
            }
        };
    }
}
