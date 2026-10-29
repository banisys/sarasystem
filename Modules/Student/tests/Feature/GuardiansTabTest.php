<?php

namespace Modules\Student\Tests\Feature;

use App\Guardians\GuardianRelationship;
use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Student\Database\Seeders\GuardianDemoSeeder;
use Modules\Student\Filament\Resources\Students\Pages\ViewStudent;
use Modules\Student\Filament\Resources\Students\RelationManagers\GuardiansRelationManager;
use Modules\Student\Filament\Resources\Students\StudentResource;
use Modules\Student\Models\Student;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The «اولیا» tab of a learner (ADR 0027).
 */
class GuardiansTabTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private Student $learner;

    private User $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::factory()->create()->makePrimary();
        $this->west = Tenant::factory()->create();

        $child = User::factory()->create(['name' => 'امیر محمدی']);
        $this->grantRole($child, $this->west, 'student');
        $this->learner = Student::factory()->for($this->west)->create(['user_id' => $child->id]);

        $this->roleWithPermissions('registrar', ['ViewAny:Student', 'View:Student', 'Update:Student']);
        $this->clerk = User::factory()->create();
        $this->makeStaff($this->clerk, $this->west);
        $this->grantRole($this->clerk, $this->west, 'registrar');
    }

    public function test_a_new_guardian_is_made_from_the_form_and_an_existing_one_is_found(): void
    {
        $existing = User::factory()->create(['national_code' => '0012345679', 'name' => 'لیلا محمدی']);

        $this->actingInPanel($this->clerk, $this->west);
        Livewire::test(GuardiansRelationManager::class, ['ownerRecord' => $this->learner, 'pageClass' => ViewStudent::class])
            ->callAction(TestAction::make('addGuardian')->table(), data: ['national_code' => '۰۰۱۳۵۴۲۴۱۹', 'relationship' => 'father'])
            ->assertNotified('کاربری با این کد ملی نیست؛ نام و موبایل ولی را وارد کنید.')
            ->callAction(TestAction::make('addGuardian')->table(), data: ['national_code' => '0013542419', 'name' => 'محمد محمدی', 'mobile' => '09121234567', 'relationship' => 'father', 'receives_sms' => true])
            ->assertHasNoActionErrors()
            ->callAction(TestAction::make('addGuardian')->table(), data: ['national_code' => '0012345679', 'relationship' => 'mother', 'receives_sms' => false])
            ->assertHasNoActionErrors();

        $father = User::query()->where('national_code', '0013542419')->sole();
        $this->assertSame('09121234567', $father->mobile);
        $links = Guardianship::query()->where('ward_id', $this->learner->user_id)->orderBy('id')->get();
        $this->assertSame([$father->id, $existing->id], $links->pluck('guardian_id')->all());
        $this->assertSame(GuardianRelationship::Mother, $links[1]->relationship);
        $this->assertFalse($links[1]->receives_sms);
        $this->assertNotSame([], $existing->roleIdsIn($this->west));
    }

    public function test_the_demo_seeder_links_the_demo_parents_to_their_children(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(GuardianDemoSeeder::class);
        $this->seed(GuardianDemoSeeder::class);

        $this->assertSame(6, Guardianship::query()->count());
        $amir = User::query()->where('email', 'student1.north@sarasystem.test')->sole();
        $this->assertSame(['محمد محمدی'], $amir->guardians()->pluck('name')->all());
        $north = Tenant::query()->where('slug', 'north')->sole();
        $this->assertTrue(Student::withoutTenantScope()->whereBelongsTo($north)->where('user_id', $amir->id)->exists());
    }

    public function test_viewers_see_the_guardians_but_do_not_manage_them(): void
    {
        $this->roleWithPermissions('viewer', ['ViewAny:Student', 'View:Student']);
        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $this->west);
        $this->grantRole($viewer, $this->west, 'viewer');
        $link = Guardianship::query()->create(['guardian_id' => User::factory()->create()->id, 'ward_id' => $this->learner->user_id, 'relationship' => 'father']);

        $this->actingInPanel($viewer, $this->west);
        Livewire::test(GuardiansRelationManager::class, ['ownerRecord' => $this->learner, 'pageClass' => ViewStudent::class])
            ->assertCanSeeTableRecords([$link])
            ->assertActionHidden(TestAction::make('addGuardian')->table())
            ->assertActionHidden(TestAction::make('removeGuardian')->table($link));

        $this->get(StudentResource::getUrl('view', ['record' => $this->learner], tenant: $this->west))->assertOk();
    }
}
