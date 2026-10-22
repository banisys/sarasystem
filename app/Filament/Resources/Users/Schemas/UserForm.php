<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                        TextInput::make('email')
                            ->label('ایمیل')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
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
                        Toggle::make('is_super_admin')
                            ->label('مدیر کل سیستم')
                            ->helperText('دسترسی کامل به همه‌ی '.config('sarasystem.tenant_plural_label').' و همه‌ی بخش‌ها.')
                            ->visible(fn (): bool => (bool) auth()->user()?->is_super_admin),
                    ]),
                Section::make(fn (): string => 'نقش‌ها در '.Tenant::current()?->name)
                    ->schema([
                        static::rolesField(),
                    ]),
            ]);
    }

    /**
     * Roles held in the current tenant. The `roles` relationship is already
     * limited to the current permission team (= tenant), and saving writes
     * the tenant id on the pivot so other tenants' roles are left untouched.
     */
    public static function rolesField(): CheckboxList
    {
        return CheckboxList::make('roles')
            ->label('نقش‌ها')
            ->relationship(
                name: 'roles',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query) => $query->assignableIn(Tenant::current())->orderBy('name'),
            )
            ->getOptionLabelFromRecordUsing(fn (Role $record): string => static::roleLabel($record))
            ->saveRelationshipsUsing(function (User $record, array $state): void {
                $record->roles()->syncWithPivotValues($state, ['tenant_id' => Tenant::current()->getKey()]);
            })
            ->required(fn (string $operation): bool => $operation === 'create')
            ->helperText('«'.static::roleLabel(Utils::getPanelUserRoleName()).'» اجازه‌ی ورود به همین پنل را در این '.config('sarasystem.tenant_label').' می‌دهد.')
            ->columns(3);
    }

    /**
     * @return array<int, string> role id => label, for roles assignable in the current tenant
     */
    public static function assignableRoleOptions(): array
    {
        return Role::query()
            ->assignableIn(Tenant::current())
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [$role->id => static::roleLabel($role)])
            ->all();
    }

    public static function roleLabel(Role|string $role): string
    {
        $name = $role instanceof Role ? $role->name : $role;

        return $name === Utils::getPanelUserRoleName() ? 'کارمند (ورود به پنل)' : Str::headline($name);
    }
}
