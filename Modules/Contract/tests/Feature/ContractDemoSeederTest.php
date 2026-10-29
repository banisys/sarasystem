<?php

namespace Modules\Contract\Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Workflow\WorkflowStep;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contract\Database\Seeders\ContractDemoSeeder;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Workflow\ContractStepExtension;
use Modules\Employee\Database\Seeders\HiringDemoSeeder;
use Modules\Student\Database\Seeders\AdmissionDemoSeeder;
use Tests\TestCase;

class ContractDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeder_adds_types_steps_and_permissions(): void
    {
        $this->seed(DemoSeeder::class);
        $headOffice = Tenant::query()->where('slug', 'head-office')->sole();
        $headOffice->makePrimary();
        User::factory()->create(['email' => 'admin@sarasystem.test']);
        $this->seed([HiringDemoSeeder::class, AdmissionDemoSeeder::class]);

        $this->seed(ContractDemoSeeder::class);
        $this->seed(ContractDemoSeeder::class);

        $this->assertSame(3, ContractType::withoutTenantScope()->where('tenant_id', $headOffice->id)->count());
        $this->assertSame(1, ContractType::withoutTenantScope()->where('is_amendment', true)->count());

        $hiring = Workflow::withoutTenantScope()->where('name', HiringDemoSeeder::WORKFLOW_NAME)->sole();
        $beforeLast = WorkflowStep::fromArray($hiring->steps[count($hiring->steps) - 2]);
        $this->assertCount(1, $beforeLast->extension(ContractStepExtension::KEY)['prepare']);

        $principal = Role::query()->whereNull('tenant_id')->where('name', 'principal')->sole();
        $this->assertTrue($principal->hasPermissionTo('Update:Contract'));
        $this->assertTrue($principal->hasPermissionTo('View:Employee'));
        $this->assertFalse($principal->hasPermissionTo('Update:ContractType'));
        $this->assertTrue(Role::query()->whereNull('tenant_id')->where('name', 'hq_member')->sole()->hasPermissionTo('Update:ContractType'));
    }
}
