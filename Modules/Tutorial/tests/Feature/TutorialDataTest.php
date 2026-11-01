<?php

namespace Modules\Tutorial\Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\Employee\Models\JobPosition;
use Modules\Site\Models\Page;
use Modules\Student\Models\Program;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Services\TutorialMode;
use Modules\Tutorial\Services\TutorialReset;
use Modules\Tutorial\Tests\Concerns\RunsTutorials;
use RuntimeException;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TutorialDataTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase, RunsTutorials;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::factory()->create()->makePrimary();
        $this->owner = User::factory()->superAdmin()->create();
    }

    public function test_rows_made_in_tutorial_mode_belong_to_the_run(): void
    {
        $before = Tenant::factory()->create();
        $run = $this->startTutorial($this->owner);
        $this->actingAs($this->owner);

        $branch = Tenant::factory()->create(['name' => 'شعبه ونک']);

        $this->assertTrue($run->made(Tenant::class)->whereKey($branch->id)->exists());
        $this->assertFalse($run->made(Tenant::class)->whereKey($before->id)->exists());
        // The branch's home page, made by the Site module, is part of it too.
        $this->assertTrue($run->made(Page::class)->where('tenant_id', $branch->id)->exists());
    }

    public function test_nothing_is_recorded_outside_tutorial_mode_or_for_someone_else(): void
    {
        $run = $this->startTutorial($this->owner);
        $this->actingAs(User::factory()->superAdmin()->create());
        Tenant::factory()->create();

        $this->actingAs($this->owner);
        session()->forget(TutorialMode::SESSION_KEY);
        Tenant::factory()->create();

        $this->assertSame(0, $run->records()->count());
    }

    public function test_starting_again_deletes_what_the_tutorial_made_and_nothing_else(): void
    {
        $existing = User::factory()->create();
        $realTenant = Tenant::factory()->create();
        $this->grantRole($existing, $realTenant, 'instructor');

        $run = $this->startTutorial($this->owner);
        $this->actingAs($this->owner);
        $branch = Tenant::factory()->create();
        $teacher = User::factory()->create();
        $this->grantRole($teacher, $branch, 'instructor');
        // An account that existed before gets a role in the tutorial branch.
        $this->grantRole($existing, $branch, 'registrar');

        $deleted = app(TutorialReset::class)->reset($run);

        $this->assertGreaterThanOrEqual(3, $deleted);
        $this->assertModelMissing($branch);
        $this->assertModelMissing($teacher);
        $this->assertModelMissing($run);
        $this->assertFalse(Page::withoutGlobalScopes()->where('tenant_id', $branch->id)->exists());
        $this->assertModelExists($existing);
        $this->assertModelExists($realTenant);
        $this->assertSame([Role::findByName('instructor')->id], $existing->fresh()->roleIdsIn($realTenant));
        $this->assertFalse(session()->has(TutorialMode::SESSION_KEY));
    }

    public function test_nothing_is_deleted_when_a_row_from_outside_still_uses_a_tutorial_row(): void
    {
        $run = $this->startTutorial($this->owner);
        $this->actingAs($this->owner);
        $branch = Tenant::factory()->create();

        session()->forget(TutorialMode::SESSION_KEY);
        Program::factory()->for($branch)->create();

        try {
            app(TutorialReset::class)->reset($run);
            $this->fail('The reset should have stopped.');
        } catch (RuntimeException) {
        }

        $this->assertModelExists($branch);
        $this->assertModelExists($run);
        $this->assertGreaterThan(0, $run->records()->count());
    }

    public function test_visitors_of_the_public_site_do_not_see_what_was_made_in_a_tutorial(): void
    {
        $primary = Tenant::primary();
        $real = Program::factory()->for($primary)->create();
        $branch = Tenant::factory()->create();
        $tutorialProgram = Program::factory()->for($primary)->create();
        $run = TutorialRun::query()->create(['user_id' => $this->owner->id, 'scenario' => 'language-institute', 'completed_missions' => []]);
        $this->madeInRun($run, $branch, $tutorialProgram);

        $this->get(route('preregister.create'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('programs', fn ($programs): bool => collect($programs)->pluck('id')->all() === [$real->id])
            ->where('options.tenants', fn ($tenants): bool => ! collect($tenants)->pluck('id')->contains($branch->id))
            ->where('site.otherTenants', fn ($tenants): bool => ! collect($tenants)->pluck('name')->contains($branch->name)));

        $this->actingAs($this->owner)->withSession([TutorialMode::SESSION_KEY => $run->id]);

        $this->get(route('preregister.create'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('programs', fn ($programs): bool => collect($programs)->pluck('id')->contains($tutorialProgram->id))
            ->where('options.tenants', fn ($tenants): bool => collect($tenants)->pluck('id')->contains($branch->id))
            ->where('tutorial.scenario', 'آموزشگاه زبان'));
    }

    public function test_the_public_form_refuses_a_tutorial_program_from_someone_else(): void
    {
        $program = Program::factory()->for(Tenant::primary())->create();
        $run = TutorialRun::query()->create(['user_id' => $this->owner->id, 'scenario' => 'language-institute', 'completed_missions' => []]);
        $this->madeInRun($run, $program);

        $this->post(route('preregister.store'), ['program_id' => $program->id])->assertSessionHasErrors('program_id');
    }

    public function test_careers_shows_tutorial_positions_only_to_their_maker(): void
    {
        $real = JobPosition::factory()->for(Tenant::primary())->create(['is_active' => true]);
        $tutorialPosition = JobPosition::factory()->for(Tenant::primary())->create(['is_active' => true]);
        $run = TutorialRun::query()->create(['user_id' => $this->owner->id, 'scenario' => 'language-institute', 'completed_missions' => []]);
        $this->madeInRun($run, $tutorialPosition);

        $this->get(route('careers.create'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('positions', fn ($positions): bool => collect($positions)->pluck('id')->all() === [$real->id]));
        $this->post(route('careers.store'), ['job_position_id' => $tutorialPosition->id])->assertSessionHasErrors('job_position_id');

        $this->actingAs($this->owner)->withSession([TutorialMode::SESSION_KEY => $run->id]);

        $this->get(route('careers.create'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('positions', fn ($positions): bool => collect($positions)->pluck('id')->contains($tutorialPosition->id)));
    }
}
