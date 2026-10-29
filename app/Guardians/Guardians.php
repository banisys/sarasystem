<?php

namespace App\Guardians;

use App\Authorization\RoleAssignment;
use App\Models\Guardianship;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Linking parents and guardians to the people they look after (ADR 0027).
 * A guardian gets the guardian role (`sarasystem.guardian_role`) in the
 * ward's tenant, so they can sign in there; it goes again when they have no
 * ward left in that tenant. Giving it follows the role assignment rules
 * (ADR 0003); linking itself doesn't change the guardian's account.
 */
class Guardians
{
    /**
     * @throws GuardianException
     */
    public function link(User $ward, User $guardian, GuardianRelationship $relationship, bool $receivesSms, Tenant $tenant, User $actor): Guardianship
    {
        if ($ward->is($guardian)) {
            throw new GuardianException('هیچ‌کس ولیِ خودش نیست.');
        }

        if (! User::query()->inTenant($tenant)->whereKey($ward->getKey())->exists()) {
            throw new GuardianException("«{$ward->name}» عضو {$tenant->name} نیست.");
        }

        $role = $this->role();
        $holdsRole = in_array($role->getKey(), $guardian->roleIdsIn($tenant), true);
        if (! $holdsRole && ! in_array($role->getKey(), RoleAssignment::assignableRoleIds($actor, $tenant), true)) {
            throw new GuardianException("شما اجازه‌ی دادن نقش «{$role->display_name}» را در {$tenant->name} ندارید.");
        }

        return DB::transaction(function () use ($ward, $guardian, $relationship, $receivesSms, $tenant, $actor, $role, $holdsRole): Guardianship {
            $guardianship = Guardianship::query()->updateOrCreate(
                ['guardian_id' => $guardian->getKey(), 'ward_id' => $ward->getKey()],
                ['relationship' => $relationship, 'receives_sms' => $receivesSms, 'created_by_id' => $actor->getKey()],
            );

            if (! $holdsRole) {
                $tenant->execute(fn () => $guardian->unsetRelation('roles')->assignRole($role));
                $guardian->unsetRelation('roles');
            }

            return $guardianship;
        });
    }

    /**
     * Removes the link, and the guardian role in the tenant when the
     * guardian has no other ward there.
     */
    public function unlink(Guardianship $guardianship, Tenant $tenant): void
    {
        DB::transaction(function () use ($guardianship, $tenant): void {
            $guardian = $guardianship->guardian;
            $guardianship->delete();

            if ($this->wardsIn($guardian, $tenant)->isEmpty()) {
                $tenant->execute(fn () => $guardian->unsetRelation('roles')->removeRole($this->role()));
                $guardian->unsetRelation('roles');
            }
        });
    }

    /**
     * The guardian's account: the user with the national code, or a new
     * one. An existing user is used as is.
     *
     * @param  array{name: string, national_code: string, mobile: string}  $data
     */
    public function findOrCreateGuardian(array $data): User
    {
        $existing = User::query()->where('national_code', $data['national_code'])->first();

        if ($existing !== null) {
            return $existing;
        }

        return User::query()->create([
            'name' => $data['name'],
            'national_code' => $data['national_code'],
            'mobile' => $data['mobile'],
            'password' => Str::password(32),
        ]);
    }

    /**
     * The guardian's wards who are members of the tenant, by name.
     *
     * @return Collection<int, User>
     */
    public function wardsIn(User $guardian, Tenant $tenant): Collection
    {
        return $guardian->wards()->inTenant($tenant)->orderBy('name')->get();
    }

    public function isGuardianOf(User $guardian, User $ward): bool
    {
        return Guardianship::query()->where('guardian_id', $guardian->getKey())->where('ward_id', $ward->getKey())->exists();
    }

    /**
     * The guardian role, made with its label if it doesn't exist yet.
     */
    public function role(): Role
    {
        $role = Role::query()->whereNull('tenant_id')->where('name', config('sarasystem.guardian_role'))->first();

        if ($role !== null) {
            return $role;
        }

        // A global role, like every role (ADR 0002), whatever team is current.
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);
        $role = Role::findOrCreate(config('sarasystem.guardian_role'), 'web');
        $role->fill(['label' => config('sarasystem.guardian_role_label')])->save();
        setPermissionsTeamId($previousTeam);

        return $role;
    }
}
