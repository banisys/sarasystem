<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class RoleLabelTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_display_name_falls_back_to_the_readable_technical_name(): void
    {
        $this->assertSame('معلم', Role::create(['name' => 'teacher', 'label' => 'معلم'])->display_name);
        $this->assertSame('Exam Manager', Role::create(['name' => 'exam_manager'])->display_name);
    }

    public function test_super_admin_creates_a_global_role_with_a_label(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'teacher', 'label' => 'معلم', 'guard_name' => 'web', 'tenant_id' => null])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'teacher')->firstOrFail();
        $this->assertSame('معلم', $role->label);
        $this->assertNull($role->tenant_id, 'A role created without a team must stay global.');
        // The published Shield pages treat unknown form keys as permission names.
        $this->assertFalse(Permission::where('name', 'معلم')->orWhere('name', 'label')->exists());
    }

    public function test_super_admin_changes_a_role_label(): void
    {
        $tenant = Tenant::factory()->create();
        $role = Role::create(['name' => 'teacher', 'label' => 'معلم']);
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['label' => 'آموزگار'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('آموزگار', $role->fresh()->label);
        $this->assertSame(0, Permission::count());
    }

    public function test_the_users_list_shows_role_labels(): void
    {
        $tenant = Tenant::factory()->create();
        Role::create(['name' => 'teacher', 'label' => 'معلم']);
        $teacher = User::factory()->create();
        $this->grantRole($teacher, $tenant, 'teacher');
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        Livewire::test(ListUsers::class)->assertSee('معلم');
    }
}
