<?php

namespace App\Authorization;

use App\Models\Role;
use Closure;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The roles every installation starts with, whatever the organization is:
 * a school chain, a language institute or a company (ADR 0039). Their keys
 * are fixed so the code can rely on them; their labels and permissions are
 * the organization's to change.
 *
 * Modules add the permissions of their screens with grant() in their
 * service provider, and other defaults (e.g. class roles) with installing().
 * A permission granted to a role is also given to the roles above it
 * (PARENTS), so a manager can always assign the roles below them (ADR 0003).
 */
class DefaultRoles
{
    public const string ORG_ADMIN = 'org_admin';

    public const string TENANT_MANAGER = 'tenant_manager';

    public const string EDUCATION_OFFICER = 'education_officer';

    public const string REGISTRAR = 'registrar';

    public const string FINANCE_OFFICER = 'finance_officer';

    public const string INSTRUCTOR = 'instructor';

    public const string LEARNER = 'learner';

    public const string STAFF = 'staff';

    /**
     * Role => the role right above it.
     *
     * @var array<string, string>
     */
    private const array PARENTS = [
        self::TENANT_MANAGER => self::ORG_ADMIN,
        self::EDUCATION_OFFICER => self::TENANT_MANAGER,
        self::REGISTRAR => self::TENANT_MANAGER,
        self::FINANCE_OFFICER => self::TENANT_MANAGER,
    ];

    /**
     * @var array<string, list<string>> role => permission names
     */
    private array $permissions = [];

    /**
     * @var list<Closure(): void>
     */
    private array $installers = [];

    /**
     * Role keys with their default labels, in the order they are listed.
     *
     * @return array<string, string>
     */
    public function definitions(): array
    {
        return [
            self::ORG_ADMIN => 'مدیر ستاد',
            self::TENANT_MANAGER => 'مدیر '.config('sarasystem.tenant_label'),
            self::EDUCATION_OFFICER => 'کارشناس آموزش',
            self::REGISTRAR => 'کارشناس ثبت‌نام',
            self::FINANCE_OFFICER => 'کارشناس مالی',
            self::INSTRUCTOR => 'مدرس',
            self::LEARNER => config('sarasystem.learner_label'),
            config('sarasystem.guardian_role') => config('sarasystem.guardian_role_label'),
            self::STAFF => 'کارمند',
        ];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->definitions());
    }

    /**
     * Gives default roles permissions, e.g. `[DefaultRoles::REGISTRAR => DefaultRoles::manage('Student')]`.
     *
     * @param  array<string, list<string>>  $permissions  role => permission names
     */
    public function grant(array $permissions): void
    {
        foreach ($permissions as $role => $names) {
            $this->permissions[$role] = array_values(array_unique([...$this->permissions[$role] ?? [], ...$names]));
        }
    }

    /**
     * Runs after the roles are installed, e.g. to make a module's own defaults.
     *
     * @param  Closure(): void  $installer
     */
    public function installing(Closure $installer): void
    {
        $this->installers[] = $installer;
    }

    /**
     * The role's default permissions, with those of the roles below it.
     *
     * @return list<string>
     */
    public function permissionsFor(string $role): array
    {
        $names = $this->permissions[$role] ?? [];

        foreach (self::PARENTS as $child => $parent) {
            if ($parent === $role) {
                $names = [...$names, ...$this->permissionsFor($child)];
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * Makes the missing default roles with their default permissions. Roles
     * that exist are left as they are, so the organization's changes stay;
     * with $addMissingPermissions they get the default permissions they
     * don't have (e.g. after a new module), and none is taken away.
     *
     * @return list<string> the roles made
     */
    public function install(bool $addMissingPermissions = false): array
    {
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        $made = [];

        foreach ($this->definitions() as $name => $label) {
            $role = Role::query()->whereNull('tenant_id')->where('name', $name)->where('guard_name', 'web')->first();

            if ($role === null) {
                $role = Role::create(['name' => $name, 'guard_name' => 'web', 'label' => $label]);
                $made[] = $name;
            } elseif (! $addMissingPermissions) {
                continue;
            }

            $role->givePermissionTo(array_map(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'), $this->permissionsFor($name)));
        }

        setPermissionsTeamId($previousTeam);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->installers as $installer) {
            $installer();
        }

        return $made;
    }

    /**
     * Whether the name is a default role's (global roles only).
     */
    public function isDefaultRoleName(?string $name): bool
    {
        return $name !== null && in_array($name, $this->names(), true);
    }

    /**
     * The usual permissions to manage a model: list, view, create, edit,
     * delete, and reorder for lists sorted by hand.
     *
     * @return list<string>
     */
    public static function manage(string $model, bool $reorder = false): array
    {
        $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete', ...($reorder ? ['Reorder'] : [])];

        return array_map(fn (string $action): string => "{$action}:{$model}", $actions);
    }

    /**
     * Permissions to list and view a model.
     *
     * @return list<string>
     */
    public static function view(string $model): array
    {
        return ["ViewAny:{$model}", "View:{$model}"];
    }
}
