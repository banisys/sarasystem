<?php

namespace Modules\Contract\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contract\Enums\SignatureMethod;
use Modules\Contract\Models\ContractType;

/**
 * @extends Factory<ContractType>
 */
class ContractTypeFactory extends Factory
{
    protected $model = ContractType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => 'قرارداد '.fake()->unique()->word(),
            'parties' => ['employee', 'learner'],
            'body' => '<p>این قرارداد میان <span data-type="mergeTag" data-id="tenant_name"></span> و <span data-type="mergeTag" data-id="person_name"></span> به کد ملی <span data-type="mergeTag" data-id="national_code"></span> از <span data-type="mergeTag" data-id="starts_on"></span> بسته می‌شود.</p>',
            'signature_method' => SignatureMethod::Either,
            'is_active' => true,
        ];
    }

    public function signedOn(SignatureMethod $method): static
    {
        return $this->state(['signature_method' => $method]);
    }
}
