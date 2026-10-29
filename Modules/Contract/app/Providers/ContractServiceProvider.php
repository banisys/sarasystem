<?php

namespace Modules\Contract\Providers;

use App\CustomFields\CustomForms;
use App\Models\Tenant;
use App\Models\User;
use App\UserPanel\UserPanel;
use App\Workflow\WorkflowStepExtensions;
use Modules\Contract\Forms\ContractForm;
use Modules\Contract\Models\Contract;
use Modules\Contract\Workflow\ContractStepExtension;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ContractServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Contract';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'contract';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Each contract type sets up its terms (ADR 0017, ADR 0020).
        CustomForms::register(new ContractForm);

        // Steps can have contracts prepared for the applicant (ADR 0020).
        WorkflowStepExtensions::register(new ContractStepExtension);

        // «قراردادها» in the user-panel header, for people with an issued
        // contract in the current tenant (ADR 0020).
        UserPanel::navigationUsing('contracts', fn (User $user, Tenant $tenant): array => Contract::query()->visibleToPerson($user)->exists()
            ? [['title' => 'قراردادها', 'url' => route('contracts.index')]]
            : []);
    }
}
