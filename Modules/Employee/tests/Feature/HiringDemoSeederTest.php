<?php

namespace Modules\Employee\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Workflow\AssigneeType;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Employee\Database\Seeders\HiringDemoSeeder;
use Modules\Employee\Models\JobPosition;
use Tests\TestCase;

class HiringDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_four_step_hiring_workflow_on_three_positions_and_can_run_again(): void
    {
        $this->seed(DemoSeeder::class);
        Tenant::query()->where('slug', 'head-office')->sole()->makePrimary();
        User::factory()->create(['email' => 'admin@sarasystem.test']);

        $this->seed(HiringDemoSeeder::class);
        $this->seed(HiringDemoSeeder::class);

        $workflow = Workflow::query()->sole();
        $steps = $workflow->stepList();
        $this->assertCount(4, $steps);
        $this->assertSame(AssigneeType::Role, $steps[0]->assigneeType);
        $this->assertSame(Tenant::query()->where('slug', 'north')->value('id'), $steps[1]->tenantId);
        $this->assertSame(AssigneeType::User, $steps[3]->assigneeType);

        $positions = JobPosition::withoutTenantScope()->get();
        $this->assertCount(3, $positions);
        $this->assertTrue($positions->every(fn (JobPosition $position): bool => $position->workflow_id === $workflow->id));
    }
}
