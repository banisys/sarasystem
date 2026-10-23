<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\AccessMap;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AccessMapTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $north;

    private Tenant $west;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->north = Tenant::factory()->create(['name' => 'شعبه شمال']);
        $this->west = Tenant::factory()->create(['name' => 'شعبه غرب']);
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_only_super_admins_can_open_it(): void
    {
        $manager = User::factory()->create();
        $this->makeStaff($manager, $this->north);

        $this->actingAs($manager)->get("/cpadmin/{$this->north->slug}/access-map")->assertForbidden();
        $this->actingAs($this->admin)->get("/cpadmin/{$this->north->slug}/access-map")->assertOk();
    }

    public function test_every_view_shows_users_with_their_roles_in_each_tenant(): void
    {
        $teacher = User::factory()->create(['name' => 'علی رضایی']);
        $this->grantRole($teacher, $this->north, 'teacher');
        $this->grantRole($teacher, $this->west, 'teacher');
        $this->actingInPanel($this->admin, $this->north);

        foreach (['tenants', 'matrix', 'roles'] as $mode) {
            Livewire::test(AccessMap::class)
                ->set('mode', $mode)
                ->assertOk()
                ->assertSee(['شعبه شمال', 'شعبه غرب', 'Teacher']);
        }

        Livewire::test(AccessMap::class)->set('mode', 'matrix')->assertSee('علی رضایی');
    }

    public function test_filters_narrow_the_users(): void
    {
        $teacher = User::factory()->create(['name' => 'علی رضایی']);
        $this->grantRole($teacher, $this->north, 'teacher');
        $student = User::factory()->create(['name' => 'سارا جعفری']);
        $this->grantRole($student, $this->west, 'student');
        $this->actingInPanel($this->admin, $this->north);

        Livewire::test(AccessMap::class)
            ->set('search', 'سارا')
            ->assertSee('سارا جعفری')
            ->assertDontSee('علی رضایی');

        Livewire::test(AccessMap::class)
            ->set('tenantId', (string) $this->north->id)
            ->assertSee('علی رضایی')
            ->assertDontSee('سارا جعفری');
    }
}
