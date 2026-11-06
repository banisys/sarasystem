<?php

namespace Modules\Contract\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Contract\Filament\Resources\ContractTypes\Pages\ListContractTypes;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The help of «انواع قرارداد» (ADR 0065).
 */
class ContractTypesHelpTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_whoever_can_open_the_page_reads_what_a_contract_is_and_what_it_is_for(): void
    {
        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $user = User::factory()->create();
        $this->makeStaff($user, $primary);
        $this->roleWithPermissions('type_viewer', ['ViewAny:ContractType']);
        $this->grantRole($user, $primary, 'type_viewer');
        $this->actingInPanel($user, $primary);

        Livewire::test(ListContractTypes::class)
            ->assertActionVisible('pageHelp')
            ->mountAction('pageHelp')
            ->assertMountedActionModalSee(['مفهوم قرارداد', 'کاربرد قرارداد در این سامانه', 'هدف از ساختن قرارداد', '{{'])
            ->assertMountedActionModalDontSee('ذخیره');
    }
}
