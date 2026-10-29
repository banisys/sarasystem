<?php

namespace Modules\Contract\Tests\Concerns;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Services\Contracts;
use Modules\Employee\Models\Employee;

trait MakesContracts
{
    protected function contractType(Tenant $primary, array $attributes = []): ContractType
    {
        return ContractType::factory()->for($primary)->create($attributes);
    }

    protected function employeeIn(Tenant $tenant, ?User $user = null): Employee
    {
        return Employee::factory()->for($tenant)->for($user ?? User::factory()->create(['mobile' => '09121234567']))->create();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function draftFor(Model $record, ContractType $type, array $state = ['starts_on' => '2026-11-01']): Contract
    {
        return app(Contracts::class)->saveDraft(null, $type, $record, $state, null);
    }

    protected function issuedFor(Model $record, ContractType $type): Contract
    {
        return app(Contracts::class)->issue($this->draftFor($record, $type), null);
    }
}
