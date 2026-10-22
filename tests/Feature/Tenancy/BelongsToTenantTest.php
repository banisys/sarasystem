<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Multitenancy\Exceptions\NoCurrentTenant;
use Tests\Fixtures\TenantNote;
use Tests\TestCase;

class BelongsToTenantTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        TenantNote::createTable();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        TenantNote::withoutTenantScope()->insert([
            ['tenant_id' => $this->tenantA->id, 'title' => 'a1'],
            ['tenant_id' => $this->tenantA->id, 'title' => 'a2'],
            ['tenant_id' => $this->tenantB->id, 'title' => 'b1'],
        ]);
    }

    public function test_queries_only_see_rows_of_the_current_tenant(): void
    {
        $this->tenantA->makeCurrent();

        $this->assertEqualsCanonicalizing(['a1', 'a2'], TenantNote::pluck('title')->all());

        $this->tenantB->makeCurrent();

        $this->assertSame(['b1'], TenantNote::pluck('title')->all());
    }

    public function test_another_tenants_row_cannot_be_found_by_id(): void
    {
        $foreignId = TenantNote::withoutTenantScope()->where('title', 'b1')->value('id');

        $this->tenantA->makeCurrent();

        $this->assertNull(TenantNote::find($foreignId));
    }

    public function test_without_a_current_tenant_nothing_matches(): void
    {
        Tenant::forgetCurrent();

        $this->assertSame(0, TenantNote::count());
    }

    public function test_new_rows_get_the_current_tenant(): void
    {
        $this->tenantB->makeCurrent();

        $note = TenantNote::create(['title' => 'new']);

        $this->assertSame($this->tenantB->id, $note->tenant_id);
    }

    public function test_creating_without_a_current_tenant_fails(): void
    {
        Tenant::forgetCurrent();

        $this->expectException(NoCurrentTenant::class);

        TenantNote::create(['title' => 'orphan']);
    }

    public function test_without_tenant_scope_sees_all_tenants(): void
    {
        $this->tenantA->makeCurrent();

        $this->assertSame(3, TenantNote::withoutTenantScope()->count());
    }

    public function test_execute_runs_in_another_tenant_and_restores_the_current_one(): void
    {
        $this->tenantA->makeCurrent();

        $titles = $this->tenantB->execute(fn () => TenantNote::pluck('title')->all());

        $this->assertSame(['b1'], $titles);
        $this->assertTrue($this->tenantA->isCurrent());
    }
}
