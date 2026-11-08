<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Filament\Resources\TeachingPlans\Pages\ListTeachingPlans;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The help of «طرح‌های درس» (ADR 0065).
 */
class TeachingPlansHelpTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_whoever_can_open_the_page_reads_what_a_teaching_plan_is_and_how_it_is_used(): void
    {
        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $user = User::factory()->create();
        $this->makeStaff($user, $primary);
        $this->roleWithPermissions('plan_viewer', ['ViewAny:TeachingPlan']);
        $this->grantRole($user, $primary, 'plan_viewer');
        $this->actingInPanel($user, $primary);

        Livewire::test(ListTeachingPlans::class)
            ->assertActionVisible('pageHelp')
            ->mountAction('pageHelp')
            ->assertMountedActionModalSee(['طرح درس چیست؟', 'کاربرد طرح درس در این سامانه', 'هدف از ساختن طرح درس', 'این صفحه چه کاری می‌کند؟'])
            ->assertMountedActionModalDontSee('ذخیره');
    }
}
