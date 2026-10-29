<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Database\Seeders\ClassDemoSeeder;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Filament\Resources\ClassRoles\ClassRoleResource;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\Classrooms\Pages\ListClassrooms;
use Modules\Class\Filament\Resources\Holidays\HolidayResource;
use Modules\Class\Filament\Resources\Holidays\Pages\ListHolidays;
use Modules\Class\Filament\Resources\Terms\TermResource;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\Term;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Terms, class roles, holidays and classes in the admin panel (ADR 0022).
 */
class ClassAdminTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create(['name' => 'غرب']);

        $this->roleWithPermissions('class_manager', [
            'ViewAny:Classroom', 'View:Classroom', 'Create:Classroom', 'Update:Classroom', 'Delete:Classroom',
            'ViewAny:Term', 'Create:Term', 'ViewAny:ClassRole', 'ViewAny:Holiday', 'Create:Holiday', 'Update:Holiday', 'Delete:Holiday',
        ]);

        $this->manager = User::factory()->create();
        foreach ([$this->primary, $this->west] as $tenant) {
            $this->makeStaff($this->manager, $tenant);
            $this->grantRole($this->manager, $tenant, 'class_manager');
        }
    }

    public function test_terms_and_class_roles_are_defined_in_the_primary_tenant_only(): void
    {
        $this->actingAs($this->manager);

        $this->get(TermResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(ClassRoleResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(TermResource::getUrl('index', tenant: $this->west))->assertForbidden();
        $this->get(ClassRoleResource::getUrl('index', tenant: $this->west))->assertForbidden();
    }

    public function test_a_class_is_created_in_the_current_tenant_with_the_terms_dates(): void
    {
        $term = Term::factory()->for($this->primary)->create(['starts_on' => '2026-09-23', 'ends_on' => '2027-06-21']);

        $this->actingInPanel($this->manager, $this->west);
        Livewire::test(ListClassrooms::class)
            ->mountAction('create')
            ->fillForm(['title' => 'هفتم الف', 'term_id' => $term->id, 'mode' => ClassMode::Online->value, 'meeting_url' => 'https://meet.example.com/7a', 'capacity' => 25])
            ->assertSchemaStateSet(['starts_on' => '2026-09-23', 'ends_on' => '2027-06-21'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $classroom = Classroom::withoutTenantScope()->where('title', 'هفتم الف')->sole();
        $this->assertSame($this->west->id, $classroom->tenant_id);
        $this->assertSame($term->id, $classroom->term_id);
        $this->assertSame(ClassMode::Online, $classroom->mode);
        $this->assertSame('2026-09-23', $classroom->starts_on->toDateString());
    }

    public function test_the_meeting_link_must_be_a_web_address(): void
    {
        $this->actingInPanel($this->manager, $this->west);
        Livewire::test(ListClassrooms::class)
            ->callAction('create', data: ['title' => 'آنلاین', 'mode' => ClassMode::Online->value, 'meeting_url' => 'javascript:alert(1)'])
            ->assertHasActionErrors(['meeting_url']);
    }

    public function test_another_tenants_classes_are_invisible(): void
    {
        $mine = Classroom::factory()->for($this->west)->create(['title' => 'کلاس غرب']);
        $theirs = Classroom::factory()->for($this->primary)->create(['title' => 'کلاس ستاد']);

        $this->actingInPanel($this->manager, $this->west);
        Livewire::test(ListClassrooms::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->get(ClassroomResource::getUrl('view', ['record' => $mine], tenant: $this->west))->assertOk();
        $this->get(ClassroomResource::getUrl('view', ['record' => $theirs], tenant: $this->west))->assertNotFound();
    }

    public function test_a_tenant_sees_the_organizations_holidays_but_edits_its_own_only(): void
    {
        $nowruz = Holiday::factory()->for($this->primary)->create(['title' => 'نوروز']);
        $own = Holiday::factory()->for($this->west)->create(['title' => 'اردو']);
        $elsewhere = Holiday::factory()->for(Tenant::factory()->create())->create(['title' => 'شعبه‌ی دیگر']);

        $this->actingInPanel($this->manager, $this->west);
        Livewire::test(ListHolidays::class)
            ->assertCanSeeTableRecords([$nowruz, $own])
            ->assertCanNotSeeTableRecords([$elsewhere])
            ->assertTableActionHidden('edit', $nowruz)
            ->assertTableActionHidden('delete', $nowruz)
            ->assertTableActionVisible('edit', $own)
            ->callAction('create', data: ['title' => 'جشن', 'starts_on' => '2026-12-01'])
            ->assertHasNoActionErrors();

        $created = Holiday::withoutTenantScope()->where('title', 'جشن')->sole();
        $this->assertSame($this->west->id, $created->tenant_id);
        $this->assertSame('2026-12-01', $created->ends_on->toDateString());
    }

    public function test_the_demo_seeder_fills_the_branches_and_the_screens_open(): void
    {
        $this->seed(DemoSeeder::class);
        $headOffice = Tenant::query()->where('slug', 'head-office')->sole();
        $headOffice->makePrimary();
        $north = Tenant::query()->where('slug', 'north')->sole();

        $this->seed(ClassDemoSeeder::class);
        $this->seed(ClassDemoSeeder::class);

        $this->assertSame(4, ClassRole::withoutTenantScope()->count());
        $main = Classroom::withoutTenantScope()->whereBelongsTo($north)->where('title', 'هفتم الف')->sole();
        $north->makeCurrent();
        $this->assertSame(3, $main->attendeeCount());
        $this->assertSame(2, $main->children()->sole()->attendeeCount());

        $admin = User::factory()->create();
        $admin->makeSuperAdmin($headOffice);
        $this->actingAs($admin);
        foreach ([TermResource::getUrl('index', tenant: $headOffice), ClassRoleResource::getUrl('index', tenant: $headOffice),
            HolidayResource::getUrl('index', tenant: $north), ClassroomResource::getUrl('index', tenant: $north),
            ClassroomResource::getUrl('view', ['record' => $main], tenant: $north)] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_a_class_with_members_is_not_deleted(): void
    {
        $empty = Classroom::factory()->for($this->west)->create();
        $used = Classroom::factory()->for($this->west)->create();
        $used->members()->create(['tenant_id' => $this->west->id, 'user_id' => $this->manager->id, 'class_role_id' => ClassRole::factory()->for($this->primary)->create()->id]);

        $this->actingInPanel($this->manager, $this->west);
        Livewire::test(ListClassrooms::class)
            ->assertTableActionVisible('delete', $empty)
            ->assertTableActionHidden('delete', $used);
    }
}
