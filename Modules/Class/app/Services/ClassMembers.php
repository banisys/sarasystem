<?php

namespace Modules\Class\Services;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;

/**
 * Adds people to a class and changes their membership, keeping the class
 * rules (ADR 0022): a member holds a role in the class's tenant (one the
 * class role allows), attendees fit the capacity, and a sub-class's
 * attendees are attendees of its parent class.
 */
class ClassMembers
{
    /**
     * @param  list<int>  $userIds
     * @return list<ClassMember>
     *
     * @throws ClassException
     */
    public function add(Classroom $classroom, ClassRole $role, array $userIds, ?CarbonInterface $joinedOn = null): array
    {
        $tenant = $this->tenantOf($classroom);
        $users = User::query()->whereKey($userIds)->orderBy('name')->get();

        foreach ($users as $user) {
            $this->checkEligible($classroom, $tenant, $role, $user);

            if ($classroom->members()->current()->where('user_id', $user->getKey())->where('class_role_id', $role->getKey())->exists()) {
                throw new ClassException("«{$user->name}» در این کلاس «{$role->title}» است.");
            }
        }

        if ($role->is_attendee && $classroom->isFull($users->count())) {
            throw new ClassException("ظرفیت کلاس ({$classroom->capacity} نفر) کافی نیست.");
        }

        return DB::transaction(fn (): array => $users->map(function (User $user) use ($classroom, $role, $joinedOn): ClassMember {
            // A member who left comes back on the same row.
            $member = $classroom->members()->where('user_id', $user->getKey())->where('class_role_id', $role->getKey())->first()
                ?? $classroom->members()->make(['user_id' => $user->getKey(), 'class_role_id' => $role->getKey()]);

            $member->fill(['tenant_id' => $classroom->tenant_id, 'joined_on' => $joinedOn ?? today(), 'left_on' => null])->save();

            return $member;
        })->all());
    }

    /**
     * @throws ClassException
     */
    public function update(ClassMember $member, ClassRole $role, ?CarbonInterface $joinedOn, ?CarbonInterface $leftOn): ClassMember
    {
        $classroom = $member->classroom;
        $user = $member->user;

        if ($joinedOn !== null && $leftOn !== null && $leftOn->isBefore($joinedOn)) {
            throw new ClassException('تاریخ خروج پیش از تاریخ ورود است.');
        }

        if ($role->isNot($member->classRole)) {
            $this->checkEligible($classroom, $this->tenantOf($classroom), $role, $user);

            if ($classroom->members()->whereKeyNot($member->getKey())->where('user_id', $user->getKey())->where('class_role_id', $role->getKey())->exists()) {
                throw new ClassException("«{$user->name}» در این کلاس «{$role->title}» است.");
            }
        }

        $staysCurrent = $leftOn === null || ! $leftOn->isBefore(today());
        if ($role->is_attendee && $staysCurrent && $classroom->isFull(exceptMemberId: $member->getKey())) {
            throw new ClassException("ظرفیت کلاس ({$classroom->capacity} نفر) پر است.");
        }

        $member->update(['class_role_id' => $role->getKey(), 'joined_on' => $joinedOn, 'left_on' => $leftOn]);

        return $member;
    }

    /**
     * @throws ClassException
     */
    private function checkEligible(Classroom $classroom, Tenant $tenant, ClassRole $role, User $user): void
    {
        if (! $role->allows($user, $tenant)) {
            throw new ClassException("«{$user->name}» نقش لازم برای «{$role->title}» را در {$tenant->name} ندارد.");
        }

        if ($role->is_attendee && $classroom->parent !== null
            && ! $classroom->parent->members()->current()->attendees()->where('user_id', $user->getKey())->exists()) {
            throw new ClassException("«{$user->name}» عضو کلاس «{$classroom->parent->title}» نیست؛ اعضای زیرکلاس از کلاس اصلی می‌آیند.");
        }
    }

    private function tenantOf(Classroom $classroom): Tenant
    {
        /** @var Tenant */
        return Tenant::query()->findOrFail($classroom->tenant_id);
    }
}
