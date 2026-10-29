<?php

namespace Tests\Feature;

use App\Guardians\GuardianException;
use App\Guardians\GuardianRelationship;
use App\Guardians\Guardians;
use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Parents and guardians of users (ADR 0027).
 */
class GuardiansTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private User $admin;

    private Guardians $guardians;

    protected function setUp(): void
    {
        parent::setUp();

        $this->west = Tenant::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->makeSuperAdmin(Tenant::factory()->create());
        $this->guardians = app(Guardians::class);
    }

    public function test_linking_gives_the_guardian_role_in_the_tenant_and_unlinking_the_last_ward_takes_it(): void
    {
        [$first, $second] = [$this->learner(), $this->learner()];
        $father = User::factory()->create();

        $link = $this->guardians->link($first, $father, GuardianRelationship::Father, true, $this->west, $this->admin);
        $this->guardians->link($second, $father, GuardianRelationship::Father, false, $this->west, $this->admin);

        $this->assertSame(GuardianRelationship::Father, $link->relationship);
        $this->assertTrue($this->holdsGuardianRole($father));
        $this->assertSame([$first->id, $second->id], $this->guardians->wardsIn($father, $this->west)->sortBy('id')->values()->modelKeys());
        $this->assertTrue($first->guardians()->whereKey($father->id)->exists());

        $this->guardians->unlink($link, $this->west);
        $this->assertTrue($this->holdsGuardianRole($father));

        $this->guardians->unlink(Guardianship::query()->sole(), $this->west);
        $this->assertFalse($this->holdsGuardianRole($father));
    }

    public function test_the_ward_must_be_a_member_of_the_tenant_and_not_the_guardian(): void
    {
        $outsider = User::factory()->create();
        $learner = $this->learner();

        $this->assertRefused(fn () => $this->guardians->link($outsider, User::factory()->create(), GuardianRelationship::Mother, true, $this->west, $this->admin), 'عضو');
        $this->assertRefused(fn () => $this->guardians->link($learner, $learner, GuardianRelationship::Other, true, $this->west, $this->admin), 'خودش');
    }

    public function test_the_actor_must_be_allowed_to_give_the_guardian_role(): void
    {
        $this->guardians->role()->givePermissionTo(Permission::findOrCreate('ViewAny:User', 'web'));
        $clerk = User::factory()->create();
        $this->grantRole($clerk, $this->west, 'clerk');

        $this->assertRefused(fn () => $this->guardians->link($this->learner(), User::factory()->create(), GuardianRelationship::Father, true, $this->west, $clerk), 'اجازه');
    }

    public function test_a_guardian_is_found_by_national_code_or_made(): void
    {
        $existing = User::factory()->create(['national_code' => '0013542419', 'name' => 'نام قبلی']);

        $this->assertTrue($existing->is($this->guardians->findOrCreateGuardian(['national_code' => '0013542419', 'name' => 'نام تازه', 'mobile' => '09120000000'])));
        $this->assertSame('نام قبلی', $existing->fresh()->name);

        $new = $this->guardians->findOrCreateGuardian(['national_code' => '0012345679', 'name' => 'مهسا کریمی', 'mobile' => '09121112233']);
        $this->assertSame('مهسا کریمی', $new->name);
        $this->assertSame('09121112233', $new->mobile);
    }

    private function learner(): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->west, 'student');

        return $user;
    }

    private function holdsGuardianRole(User $user): bool
    {
        return in_array($this->guardians->role()->id, $user->roleIdsIn($this->west), true);
    }

    private function assertRefused(callable $operation, string $message): void
    {
        try {
            $operation();
        } catch (GuardianException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());

            return;
        }

        $this->fail("No error «{$message}».");
    }
}
