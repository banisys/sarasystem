<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Authorization\RoleAssignment;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\IranianMobile;
use App\Rules\NationalCode;
use BezhanSalleh\FilamentShield\Support\Utils;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات کاربر')
                    ->description('این اطلاعات بین همه‌ی '.config('sarasystem.tenant_plural_label').' مشترک است.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('نام')
                            ->required()
                            ->maxLength(255),
                        // Login identifiers (ADR 0004): at least one is required.
                        TextInput::make('mobile')
                            ->label('موبایل')
                            ->tel()
                            ->rule(new IranianMobile)
                            ->requiredWithoutAll(['national_code', 'email'])
                            ->helperText('ممکن است بین چند حساب مشترک باشد (مثلاً موبایل والد).')
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('national_code')
                            ->label('کد ملی')
                            ->rule(new NationalCode)
                            ->unique(ignoreRecord: true)
                            ->requiredWithoutAll(['mobile', 'email'])
                            ->validationMessages([
                                'unique' => 'این کد ملی قبلاً ثبت شده است؛ از «افزودن کاربر موجود» استفاده کنید.',
                            ])
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('email')
                            ->label('ایمیل')
                            ->email()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->requiredWithoutAll(['mobile', 'national_code'])
                            ->validationMessages([
                                'unique' => 'این ایمیل قبلاً ثبت شده است؛ از «افزودن کاربر موجود» استفاده کنید.',
                            ])
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('password')
                            ->label('رمز عبور')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'برای تغییر ندادن، خالی بگذارید.' : null),
                    ]),
                Section::make(fn (): string => 'نقش‌ها در '.Tenant::current()?->name)
                    ->schema([
                        static::rolesField(),
                    ]),
            ]);
    }

    /**
     * Roles held in the current tenant. The `roles` relationship is already
     * limited to the current permission team (= tenant). Options and saving
     * are limited to the roles the acting user may assign (ADR 0003), and
     * saving writes the tenant id on the pivot so other tenants are untouched.
     */
    public static function rolesField(): CheckboxList
    {
        return CheckboxList::make('roles')
            ->label('نقش‌ها')
            ->relationship(
                name: 'roles',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query) => $query
                    ->whereKey(RoleAssignment::assignableRoleIds(auth()->user(), Tenant::current()))
                    ->orderBy('name'),
            )
            ->getOptionLabelFromRecordUsing(fn (Role $record): string => static::roleLabel($record))
            ->disabled(fn (?User $record): bool => static::isEditingOwnRoles($record))
            ->disableOptionWhen(fn (string $value, ?User $record): bool => $record?->is(auth()->user()) && static::isSuperAdminRole((int) $value))
            ->rule(fn (): Closure => static::onlyAssignableRolesRule())
            ->saveRelationshipsUsing(function (User $record, ?array $state): void {
                if (static::isEditingOwnRoles($record)) {
                    return;
                }

                $assignable = RoleAssignment::assignableRoleIds(auth()->user(), Tenant::current());
                $kept = $record->roles()->pluck('roles.id')->diff($assignable);

                // A super admin can't take super_admin away from themselves (ADR 0007).
                if ($record->is(auth()->user())) {
                    $kept = $kept->merge($record->roles()->where('name', Role::SUPER_ADMIN)->whereNull('roles.tenant_id')->pluck('roles.id'));
                }
                $chosen = collect($state ?? [])->map(fn ($id): int => (int) $id)->intersect($assignable);

                $record->roles()->syncWithPivotValues($chosen->merge($kept)->unique()->all(), ['tenant_id' => Tenant::current()->getKey()]);
            })
            ->required(fn (string $operation): bool => $operation === 'create')
            ->helperText(fn (?User $record): string => static::isEditingOwnRoles($record)
                ? 'نقش‌های خودتان را نمی‌توانید تغییر دهید.'
                : 'فقط نقش‌هایی نمایش داده می‌شوند که همه‌ی دسترسی‌هایشان را خودتان دارید. «'.static::roleLabel(Utils::getPanelUserRoleName()).'» اجازه‌ی ورود به همین پنل را در این '.config('sarasystem.tenant_label').' می‌دهد.')
            ->columns(3);
    }

    /**
     * Server-side check that every submitted role id is assignable by the
     * acting user; Filament does not validate relationship options itself.
     */
    public static function onlyAssignableRolesRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $assignable = RoleAssignment::assignableRoleIds(auth()->user(), Tenant::current());

            if (collect($value ?? [])->map(fn ($id): int => (int) $id)->diff($assignable)->isNotEmpty()) {
                $fail('شما اجازه‌ی دادن یکی از نقش‌های انتخاب‌شده را ندارید.');
            }
        };
    }

    /**
     * @return array<int, string> role id => label, for roles the acting user may assign in the current tenant
     */
    public static function assignableRoleOptions(): array
    {
        return RoleAssignment::assignableRoles(auth()->user(), Tenant::current())
            ->mapWithKeys(fn (Role $role): array => [$role->id => static::roleLabel($role)])
            ->all();
    }

    /**
     * Nobody but a super admin changes their own roles (ADR 0003).
     */
    private static function isEditingOwnRoles(?User $record): bool
    {
        $actor = auth()->user();

        return $record !== null && $record->is($actor) && ! $actor->isSuperAdmin();
    }

    private static function isSuperAdminRole(int $roleId): bool
    {
        return Role::query()->whereKey($roleId)->whereNull('tenant_id')->where('name', Role::SUPER_ADMIN)->exists();
    }

    public static function roleLabel(Role|string $role): string
    {
        if (is_string($role)) {
            $role = Role::query()->whereNull('tenant_id')->where('name', $role)->first() ?? $role;
        }

        return $role instanceof Role ? $role->display_name : Str::headline($role);
    }
}
