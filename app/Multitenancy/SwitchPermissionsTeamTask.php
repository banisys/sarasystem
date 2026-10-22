<?php

namespace App\Multitenancy;

use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/**
 * Keeps Spatie permission's current team in step with the current tenant,
 * so role and permission checks always use the roles held in that tenant.
 */
class SwitchPermissionsTeamTask implements SwitchTenantTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        setPermissionsTeamId($tenant->getKey());
    }

    public function forgetCurrent(): void
    {
        setPermissionsTeamId(null);
    }
}
