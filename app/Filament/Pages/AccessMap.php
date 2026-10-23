<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * Super-admin overview of who holds which role in which tenant, across all
 * tenants: by tenant (cards), as a user × tenant matrix, and by role.
 * panel_user is shown as a "staff" mark next to the user, not as a role group.
 */
class AccessMap extends Page
{
    /**
     * Role colors (Tailwind classes, light and dark), picked by role order.
     * super_admin always uses the first one.
     */
    private const array COLORS = [
        'bg-rose-100 text-rose-800 ring-rose-300 dark:bg-rose-500/20 dark:text-rose-200 dark:ring-rose-500/40',
        'bg-violet-100 text-violet-800 ring-violet-300 dark:bg-violet-500/20 dark:text-violet-200 dark:ring-violet-500/40',
        'bg-sky-100 text-sky-800 ring-sky-300 dark:bg-sky-500/20 dark:text-sky-200 dark:ring-sky-500/40',
        'bg-emerald-100 text-emerald-800 ring-emerald-300 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-500/40',
        'bg-amber-100 text-amber-800 ring-amber-300 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-500/40',
        'bg-teal-100 text-teal-800 ring-teal-300 dark:bg-teal-500/20 dark:text-teal-200 dark:ring-teal-500/40',
        'bg-fuchsia-100 text-fuchsia-800 ring-fuchsia-300 dark:bg-fuchsia-500/20 dark:text-fuchsia-200 dark:ring-fuchsia-500/40',
        'bg-orange-100 text-orange-800 ring-orange-300 dark:bg-orange-500/20 dark:text-orange-200 dark:ring-orange-500/40',
        'bg-indigo-100 text-indigo-800 ring-indigo-300 dark:bg-indigo-500/20 dark:text-indigo-200 dark:ring-indigo-500/40',
        'bg-lime-100 text-lime-800 ring-lime-300 dark:bg-lime-500/20 dark:text-lime-200 dark:ring-lime-500/40',
        'bg-cyan-100 text-cyan-800 ring-cyan-300 dark:bg-cyan-500/20 dark:text-cyan-200 dark:ring-cyan-500/40',
        'bg-pink-100 text-pink-800 ring-pink-300 dark:bg-pink-500/20 dark:text-pink-200 dark:ring-pink-500/40',
    ];

    /**
     * Solid role colors for dots and bars, in the same order as COLORS.
     */
    private const array DOTS = [
        'bg-rose-500', 'bg-violet-500', 'bg-sky-500', 'bg-emerald-500', 'bg-amber-500', 'bg-teal-500',
        'bg-fuchsia-500', 'bg-orange-500', 'bg-indigo-500', 'bg-lime-500', 'bg-cyan-500', 'bg-pink-500',
    ];

    /** Users listed per role in a tenant card before "+N more". */
    public const int USERS_PER_GROUP = 24;

    /** Rows in the matrix before it asks to narrow the filters. */
    public const int MATRIX_ROWS = 200;

    protected string $view = 'filament.pages.access-map';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static ?int $navigationSort = 95;

    protected static ?string $title = 'نقشه‌ی دسترسی';

    #[Url]
    public string $mode = 'tenants';

    #[Url]
    public ?string $tenantId = null;

    #[Url]
    public ?string $roleId = null;

    #[Url]
    public string $search = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tenants = Tenant::query()->orderByDesc('is_active')->orderBy('name')->get()->keyBy('id');
        $roles = Role::query()->with('permissions:id,name')->orderBy('id')->get()->keyBy('id');
        $panelUserRoleId = $roles->firstWhere(fn (Role $role): bool => $role->tenant_id === null && $role->name === Utils::getPanelUserRoleName())?->id;

        $assignments = $this->assignments();
        $users = User::query()->whereKey($assignments->pluck('user_id')->unique())->orderBy('name')->get(['id', 'name', 'mobile', 'email'])->keyBy('id');

        // tenant => role => user ids; user => tenant => role ids; tenant => staff user ids
        $byTenant = [];
        $byUser = [];
        $staff = [];

        foreach ($assignments as $row) {
            if ($row->role_id === $panelUserRoleId) {
                $staff[$row->tenant_id][$row->user_id] = true;

                continue;
            }

            $byTenant[$row->tenant_id][$row->role_id][] = $row->user_id;
            $byUser[$row->user_id][$row->tenant_id][] = $row->role_id;
        }

        // Staff with no other role in the tenant still show up there.
        foreach ($staff as $tenantId => $staffIds) {
            foreach (array_keys($staffIds) as $userId) {
                $byUser[$userId][$tenantId] ??= [];
            }
        }

        $visibleTenants = $this->tenantId ? $tenants->only([(int) $this->tenantId]) : $tenants;

        return [
            'tenants' => $tenants,
            'visibleTenants' => $visibleTenants,
            'roles' => $roles,
            'users' => $users,
            'byTenant' => $byTenant,
            'byUser' => $byUser,
            'staff' => $staff,
            'colors' => array_map(fn (int $index): string => self::COLORS[$index], $colorIndexes = $this->roleColorIndexes($roles)),
            'dots' => array_map(fn (int $index): string => self::DOTS[$index], $colorIndexes),
            'panelUserRoleId' => $panelUserRoleId,
            'stats' => [
                'tenants' => $tenants->where('is_active', true)->count(),
                'inactiveTenants' => $tenants->where('is_active', false)->count(),
                'users' => $users->count(),
                'roles' => $roles->count(),
                'multiTenantUsers' => collect($byUser)->filter(fn (array $tenantRoles): bool => count($tenantRoles) > 1)->count(),
            ],
        ];
    }

    /**
     * Membership rows (user, tenant, role) matching the filters. With a role
     * filter, the matching users' other roles are kept, so their whole place
     * in the organization stays visible.
     *
     * @return \Illuminate\Support\Collection<int, object{user_id: int, tenant_id: int, role_id: int}>
     */
    private function assignments(): \Illuminate\Support\Collection
    {
        $table = config('permission.table_names.model_has_roles');
        $userKey = config('permission.column_names.model_morph_key');

        $rows = DB::table($table)
            ->where('model_type', (new User)->getMorphClass())
            ->when($this->tenantId, fn ($query) => $query->where('tenant_id', (int) $this->tenantId))
            ->when(filled($this->search), fn ($query) => $query->whereIn($userKey, User::query()
                ->where(fn ($query) => $query
                    ->whereLike('name', '%'.trim($this->search).'%')
                    ->orWhere(fn ($query) => $query->whereLoginIdentifier($this->search)))
                ->select('id')))
            ->get(['tenant_id', 'role_id', "{$userKey} as user_id"]);

        if ($this->roleId) {
            $holders = $rows->where('role_id', (int) $this->roleId)->pluck('user_id')->unique();
            $rows = $rows->whereIn('user_id', $holders);
        }

        return $rows->map(fn (object $row): object => (object) [
            'user_id' => (int) $row->user_id,
            'tenant_id' => (int) $row->tenant_id,
            'role_id' => (int) $row->role_id,
        ])->values();
    }

    /**
     * @param  Collection<int, Role>  $roles
     * @return array<int, int> role id => index into COLORS / DOTS
     */
    private function roleColorIndexes(Collection $roles): array
    {
        $indexes = [];
        $next = 0;

        foreach ($roles as $role) {
            $indexes[$role->id] = $role->name === Role::SUPER_ADMIN && $role->tenant_id === null
                ? 0
                : 1 + ($next++ % (count(self::COLORS) - 1));
        }

        return $indexes;
    }

    public function userUrl(int $userId, Tenant $tenant): ?string
    {
        return $tenant->is_active ? UserResource::getUrl('edit', ['record' => $userId, 'tenant' => $tenant]) : null;
    }
}
