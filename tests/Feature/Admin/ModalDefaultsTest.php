<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ModalDefaultsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_modals_close_only_with_their_close_button(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        $actions = Livewire::test(ListUsers::class)->instance()->getTable()->getFlatRecordActions();
        $this->assertNotEmpty($actions);

        foreach ($actions as $action) {
            $this->assertTrue($action->hasModalCloseButton());
            $this->assertFalse($action->isModalClosedByClickingAway());
            $this->assertFalse($action->isModalClosedByEscaping());
        }
    }
}
