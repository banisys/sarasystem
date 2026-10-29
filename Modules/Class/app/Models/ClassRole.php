<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Class\Database\Factories\ClassRoleFactory;

/**
 * What a person is in a class, e.g. «فراگیر», «مدرس», «ناظر» or «پشتیبان»
 * (ADR 0022). Separate from the system roles: an employee may supervise one
 * class and teach another. Defined once, in the primary tenant. Its flags
 * say whether its members are attendees (attendance is taken for them and
 * they count toward the capacity), take the attendance, or record what
 * a session covered. It may be limited to holders of some system roles.
 *
 * @property list<int> $allowed_role_ids
 */
#[Fillable(['tenant_id', 'title', 'is_attendee', 'takes_attendance', 'records_content', 'allowed_role_ids', 'sort'])]
class ClassRole extends Model
{
    /** @use HasFactory<ClassRoleFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ClassRoleFactory
    {
        return ClassRoleFactory::new();
    }

    /**
     * Roles of the primary tenant, from anywhere.
     *
     * @return Builder<ClassRole>
     */
    public static function definedRoles(): Builder
    {
        return static::withoutTenantScope()->where('tenant_id', Tenant::primary()?->getKey())->orderBy('sort')->orderBy('id');
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::definedRoles()->pluck('title', 'id')->all();
    }

    /**
     * Whether the user may hold this role in a class of the tenant.
     */
    public function allows(User $user, Tenant $tenant): bool
    {
        $roleIds = $user->roleIdsIn($tenant);

        return $roleIds !== [] && ($this->allowed_role_ids === [] || array_intersect($roleIds, $this->allowed_role_ids) !== []);
    }

    /**
     * Users who may hold this role in a class of the tenant.
     *
     * @return Builder<User>
     */
    public function eligibleUsers(Tenant $tenant): Builder
    {
        $query = User::query()->inTenant($tenant);

        if ($this->allowed_role_ids !== []) {
            $query->whereExists(fn ($roles) => $roles
                ->selectRaw('1')
                ->from(config('permission.table_names.model_has_roles'))
                ->whereColumn(config('permission.column_names.model_morph_key'), 'users.id')
                ->where('model_type', (new User)->getMorphClass())
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('role_id', $this->allowed_role_ids));
        }

        return $query;
    }

    /**
     * Members of every tenant hold it.
     *
     * @return HasMany<ClassMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ClassMember::class)->withoutGlobalScope(TenantScope::class);
    }

    protected function casts(): array
    {
        return [
            'is_attendee' => 'boolean',
            'takes_attendance' => 'boolean',
            'records_content' => 'boolean',
            'allowed_role_ids' => 'array',
        ];
    }
}
