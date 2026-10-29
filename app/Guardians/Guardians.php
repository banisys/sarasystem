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
 * (ADR 0003); linking itself doesn't change the guardian's account. A
 * ward without a mobile signs in with one guardian's, kept in step with
 * it (ADR 0028).
 */
class Guardians
{
    /**
     * @throws GuardianException
     */
    public function link(User $ward, User $guardian, GuardianRelationship $relationship, bool $receivesSms, Tenant $tenant, User $actor, bool $providesLoginMobile = false): Guardianship
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

        return DB::transaction(function () use ($ward, $guardian, $relationship, $receivesSms, $tenant, $actor, $role, $holdsRole, $providesLoginMobile): Guardianship {
            $guardianship = Guardianship::query()->updateOrCreate(
                ['guardian_id' => $guardian->getKey(), 'ward_id' => $ward->getKey()],
                ['relationship' => $relationship, 'receives_sms' => $receivesSms, 'created_by_id' => $actor->getKey()],
            );

            if ($providesLoginMobile) {
                $this->useLoginMobileOf($ward, $guardianship);
            }

            if (! $holdsRole) {
                $tenant->execute(fn () => $guardian->unsetRelation('roles')->assignRole($role));
                $guardian->unsetRelation('roles');
            }

            return $guardianship;
        });
    }

    /**
     * Whose mobile the ward signs in with: a guardian's (kept in step with
     * it), or — with null — the ward's own, set to the given mobile.
     *
     * @throws GuardianException
     */
    public function useLoginMobileOf(User $ward, ?Guardianship $guardianship, ?string $ownMobile = null): void
    {
        if ($guardianship !== null && $guardianship->ward_id !== $ward->getKey()) {
            throw new GuardianException('این ولی، ولیِ این '.config('sarasystem.learner_label').' نیست.');
        }

        if ($guardianship !== null && blank($guardianship->guardian->mobile)) {
            throw new GuardianException("«{$guardianship->guardian->name}» موبایل ندارد.");
        }

        DB::transaction(function () use ($ward, $guardianship, $ownMobile): void {
            Guardianship::query()->where('ward_id', $ward->getKey())
                ->when($guardianship, fn ($query) => $query->whereKeyNot($guardianship->getKey()))
                ->update(['provides_login_mobile' => false]);

            if ($guardianship !== null) {
                $guardianship->update(['provides_login_mobile' => true]);
                $ward->forceFill(['mobile' => $guardianship->guardian->mobile])->saveQuietly();
            } elseif ($ownMobile !== null) {
                $ward->forceFill(['mobile' => $ownMobile])->saveQuietly();
            }
        });
    }

    /**
     * After a user's mobile changed: their wards who sign in with it follow;
     * a ward who changed their own mobile no longer uses a guardian's.
     */
    public function mobileChanged(User $user): void
    {
        Guardianship::query()->where('guardian_id', $user->getKey())->where('provides_login_mobile', true)->with('ward')->get()
            ->each(fn (Guardianship $guardianship) => $guardianship->ward->forceFill(['mobile' => $user->mobile])->saveQuietly());

        Guardianship::query()->where('ward_id', $user->getKey())->where('provides_login_mobile', true)->with('guardian')->get()
            ->filter(fn (Guardianship $guardianship): bool => $guardianship->guardian->mobile !== $user->mobile)
            ->each(fn (Guardianship $guardianship) => $guardianship->update(['provides_login_mobile' => false]));
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
