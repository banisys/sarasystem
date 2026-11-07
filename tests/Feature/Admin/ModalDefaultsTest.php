<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ModalDefaultsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_modals_close_with_their_close_button_only(): void
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

    public function test_a_modal_with_a_close_button_can_go_full_screen(): void
    {
        $html = Blade::render('<x-filament::modal id="sample" heading="عنوان">متن</x-filament::modal>');

        $this->assertStringContainsString('fi-modal-fullscreen-btn', $html);
        $this->assertStringContainsString("'fi-modal-fullscreen': \$store.adminModal.isFullscreen", $html);
    }

    public function test_a_modal_already_full_screen_or_without_a_close_button_has_no_full_screen_button(): void
    {
        foreach (['width="screen"', ':close-button="false"', ':alert="true"'] as $attribute) {
            $html = Blade::render('<x-filament::modal id="sample" heading="عنوان" '.$attribute.'>متن</x-filament::modal>');

            $this->assertStringNotContainsString('fi-modal-fullscreen', $html);
        }
    }

    public function test_the_full_screen_choice_is_kept_per_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->superAdmin()->create();
        $this->actingInPanel($user, $tenant);

        $this->assertStringContainsString(
            '"admin-modal-fullscreen.'.$user->id.'"',
            view('filament.admin.modal-size-preference')->render(),
        );
    }

    public function test_the_modal_override_is_based_on_the_installed_filament_view(): void
    {
        $this->assertSame(
            '31d8dbf068ad26bab559f1ee5910407da0d3f08b',
            sha1_file(base_path('vendor/filament/support/resources/views/components/modal/index.blade.php')),
            'Filament changed its modal: copy it again to resources/views/vendor/filament/components/modal/index.blade.php and repeat the changes (ADR 0060).',
        );
    }
}
