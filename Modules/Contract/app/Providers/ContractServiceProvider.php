<?php

namespace Modules\Contract\Providers;

use App\Contract\ContractParties;
use App\CustomFields\CustomForms;
use App\Filament\Support\ModelRelationManagers;
use App\Models\Tenant;
use App\Models\User;
use App\UserPanel\UserPanel;
use App\Workflow\WorkflowStepExtensions;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Contract\Console\SendContractReminders;
use Modules\Contract\Filament\RelationManagers\ContractsRelationManager;
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
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SendContractReminders::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Reminders of contracts about to end or waiting for a signature
     * (ADR 0021).
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(SendContractReminders::class)->dailyAt('08:00')->withoutOverlapping();
    }

    public function boot(): void
    {
        parent::boot();

        // Each contract type sets up its terms (ADR 0017, ADR 0020).
        CustomForms::register(new ContractForm);

        // Steps can have contracts prepared for the applicant (ADR 0020).
        WorkflowStepExtensions::register(new ContractStepExtension);

        // A «قراردادها» tab on each party's member page (employee, learner).
        // After every provider booted: the parties are registered by their
        // own modules.
        $this->app->booted(function (): void {
            foreach (ContractParties::all() as $party) {
                $party->memberModel()::resolveRelationUsing(ContractsRelationManager::RELATIONSHIP, fn (Model $member): MorphMany => $member->morphMany(Contract::class, 'contractable'));
                ModelRelationManagers::register($party->memberModel(), ContractsRelationManager::class);
            }
        });

        // «قراردادها» in the user-panel header, for people with an issued
        // contract in the current tenant (ADR 0020).
        UserPanel::navigationUsing('contracts', fn (User $user, Tenant $tenant): array => Contract::query()->visibleToPerson($user)->exists()
            ? [['title' => 'قراردادها', 'url' => route('contracts.index')]]
            : []);
    }
}
