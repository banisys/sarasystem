<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Tenants\Pages\ManageTenants;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Support\PanelTable;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PanelTableTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_row_actions_are_one_dropdown_in_the_first_column(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        foreach ([ListUsers::class, ManageTenants::class] as $page) {
            $table = Livewire::test($page)->instance()->getTable();

            $this->assertInstanceOf(PanelTable::class, $table);
            $this->assertSame(RecordActionsPosition::BeforeColumns, $table->getRecordActionsPosition());
            $this->assertCount(1, $table->getRecordActions());
            $this->assertInstanceOf(ActionGroup::class, $table->getRecordActions()[0]);
            $this->assertArrayHasKey('edit', $table->getFlatRecordActions());
        }
    }

    public function test_clicking_a_row_does_nothing(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $this->actingInPanel($admin, $tenant);

        $table = Livewire::test(ListUsers::class)->instance()->getTable();

        $this->assertNull($table->getRecordUrl($admin));
        $this->assertNull($table->getRecordAction($admin));
    }

    public function test_panel_wide_table_defaults_still_apply(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        $table = Livewire::test(ListUsers::class)->instance()->getTable();

        $this->assertSame(FiltersLayout::Dropdown, $table->getFiltersLayout());
        $this->assertSame(25, $table->getDefaultPaginationPageOption());
    }

    public function test_tables_have_no_search_box(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        foreach ([ListUsers::class, ManageTenants::class] as $page) {
            $this->assertFalse(Livewire::test($page)->instance()->getTable()->isSearchable());
        }
    }

    public function test_top_bar_has_no_global_search(): void
    {
        $this->assertNull(Filament::getPanel('admin')->getGlobalSearchProvider());
    }
}
