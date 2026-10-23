<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_branches_and_users_and_can_run_again(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(4, Tenant::count());
        $this->assertSame(35, User::count());

        $teacher = User::where('email', 'teacher1.north@sarasystem.test')->firstOrFail();
        $this->assertEqualsCanonicalizing(['north', 'center'], $teacher->tenants()->pluck('slug')->all());
        $this->assertTrue($teacher->canAccessTenant(Tenant::where('slug', 'north')->firstOrFail()));

        $student = User::where('email', 'student1.north@sarasystem.test')->firstOrFail();
        $this->assertFalse($student->staffTenants()->exists());
    }
}
