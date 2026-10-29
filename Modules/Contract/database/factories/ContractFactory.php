<?php

namespace Modules\Contract\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Models\Contract;

/**
 * A draft; give it a type, a contractable record and its tenant.
 *
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ContractStatus::Draft,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'answers' => [],
        ];
    }
}
