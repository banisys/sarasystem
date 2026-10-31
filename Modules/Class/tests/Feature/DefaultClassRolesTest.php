<?php

namespace Modules\Class\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Class\Models\ClassRole;
use Tests\TestCase;

class DefaultClassRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_installing_the_default_roles_makes_the_class_roles_in_the_primary_tenant(): void
    {
        $primary = Tenant::factory()->create();
        $primary->makePrimary();

        app(DefaultRoles::class)->install();

        $roles = ClassRole::definedRoles()->get();
        $this->assertSame([config('sarasystem.learner_label'), 'مدرس', 'ناظر', 'پشتیبان'], $roles->pluck('title')->all());
        $this->assertTrue($roles[0]->is_attendee);
        $this->assertSame([Role::findByName(DefaultRoles::LEARNER)->id], $roles[0]->allowed_role_ids);
        $this->assertTrue($roles[1]->records_content);
        $this->assertSame([Role::findByName(DefaultRoles::INSTRUCTOR)->id], $roles[1]->allowed_role_ids);
    }

    public function test_class_roles_are_not_made_when_the_organization_has_its_own(): void
    {
        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        ClassRole::factory()->create(['tenant_id' => $primary->id, 'title' => 'دانشجو']);

        app(DefaultRoles::class)->install();

        $this->assertSame(['دانشجو'], ClassRole::definedRoles()->pluck('title')->all());
    }
}
