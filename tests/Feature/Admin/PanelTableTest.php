<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ManageModules;
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

    public function test_the_first_column_is_the_row_number(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        $table = Livewire::test(ListUsers::class)->instance()->getTable();

        $this->assertSame('rowNumber', array_key_first($table->getColumns()));
    }

    public function test_timestamps_are_visible_sortable_and_not_duplicated(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        foreach ([ListUsers::class, ManageTenants::class] as $page) {
            $table = Livewire::test($page)->instance()->getTable();

            foreach (['created_at', 'updated_at'] as $name) {
                $column = $table->getColumn($name);

                $this->assertTrue($column->isVisible());
                $this->assertFalse($column->isToggledHidden());
                $this->assertTrue($column->isSortable());
                $this->assertCount(1, array_filter($table->getColumnsLayout(), fn ($component): bool => $component->getName() === $name));
            }
        }
    }

    public function test_timestamps_sort_in_both_directions_without_showing_another_tenants_users(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);
        $older = User::factory()->create(['created_at' => '2026-01-01 09:00:00', 'updated_at' => '2026-03-01 09:00:00']);
        $newer = User::factory()->create(['created_at' => '2026-02-01 09:00:00', 'updated_at' => '2026-02-01 09:00:00']);
        $outsider = User::factory()->create();
        $this->makeStaff($older, $tenant);
        $this->makeStaff($newer, $tenant);
        $this->makeStaff($outsider, Tenant::factory()->create());

        Livewire::test(ListUsers::class)
            ->sortTable('created_at', 'asc')
            ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
            ->assertCanNotSeeTableRecords([$outsider])
            ->sortTable('created_at', 'desc')
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->sortTable('updated_at', 'asc')
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->sortTable('updated_at', 'desc')
            ->assertCanSeeTableRecords([$older, $newer], inOrder: true);
    }

    public function test_array_backed_tables_do_not_show_database_timestamps(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        $table = Livewire::test(ManageModules::class)->instance()->getTable();

        $this->assertFalse($table->getColumn('created_at')->isVisible());
        $this->assertFalse($table->getColumn('updated_at')->isVisible());
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
