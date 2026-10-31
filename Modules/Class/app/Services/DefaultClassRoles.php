<?php

namespace Modules\Class\Services;

use App\Authorization\DefaultRoles;
use App\Models\Role;
use App\Models\Tenant;
use Modules\Class\Models\ClassRole;

/**
 * The class roles an installation starts with (ADR 0039): learner, teacher,
 * supervisor and supporter, made in the primary tenant with the default
 * system roles. Made only while there is no class role at all, so the
 * organization's own changes stay.
 */
class DefaultClassRoles
{
    public function install(): void
    {
        $primary = Tenant::primary();

        if ($primary === null || ClassRole::withoutTenantScope()->exists()) {
            return;
        }

        $roleId = fn (string $name): array => Role::query()->whereNull('tenant_id')->where('name', $name)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        // tenant_id set here: seeders run without model events (BelongsToTenant).
        $roles = [
            ['title' => config('sarasystem.learner_label'), 'is_attendee' => true, 'allowed_role_ids' => $roleId(DefaultRoles::LEARNER)],
            ['title' => 'مدرس', 'takes_attendance' => true, 'records_content' => true, 'allowed_role_ids' => $roleId(DefaultRoles::INSTRUCTOR)],
            ['title' => 'ناظر', 'takes_attendance' => true, 'allowed_role_ids' => []],
            ['title' => 'پشتیبان', 'allowed_role_ids' => []],
        ];

        foreach ($roles as $index => $attributes) {
            ClassRole::withoutTenantScope()->create([...$attributes, 'tenant_id' => $primary->getKey(), 'sort' => $index + 1]);
        }
    }
}
