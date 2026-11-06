<?php

namespace Tests\Feature\Admin;

use App\Authorization\DefaultRoles;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\PageHelp;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fixtures\HelpedPage;
use Tests\TestCase;

/**
 * The help icon beside a panel page's title (ADR 0065).
 */
class PageHelpTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private const string FORM_NOTE = 'اگر متن را خالی کنید';

    private Tenant $primary;

    private Tenant $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->branch = Tenant::factory()->create();
    }

    private function staff(Tenant $tenant, bool $mayEdit = false): User
    {
        $user = User::factory()->create();
        $this->makeStaff($user, $tenant);

        if ($mayEdit) {
            $this->roleWithPermissions('help_editor', [PageHelp::EDIT_PERMISSION]);
            $this->grantRole($user, $tenant, 'help_editor');
        }

        return $user;
    }

    public function test_the_icon_sits_in_the_heading_next_to_the_title(): void
    {
        $this->actingInPanel($this->staff($this->primary), $this->primary);

        $page = Livewire::test(HelpedPage::class)->assertActionVisible('pageHelp');

        $this->assertMatchesRegularExpression(
            '/<h1 class="fi-header-heading">.*صفحه‌ی نمونه.*mountAction\(\'pageHelp\'.*<\/h1>/s',
            $page->html(),
        );
    }

    public function test_someone_without_the_permission_reads_the_text_and_has_no_form(): void
    {
        $this->actingInPanel($this->staff($this->primary), $this->primary);

        Livewire::test(HelpedPage::class)
            ->mountAction('pageHelp')
            ->assertMountedActionModalSee('خط اول راهنما')
            ->assertMountedActionModalDontSee(['ذخیره', self::FORM_NOTE]);
    }

    public function test_someone_without_the_permission_cannot_save_even_by_calling_the_action(): void
    {
        $this->actingInPanel($this->staff($this->primary), $this->primary);

        Livewire::test(HelpedPage::class)
            ->mountAction('pageHelp')
            ->set('mountedActions.0.data.body', 'متن جعلی')
            ->call('callMountedAction');

        $this->assertSame(0, PageHelp::query()->count());
    }

    public function test_someone_with_the_permission_edits_the_text_and_others_read_it(): void
    {
        $editor = $this->staff($this->primary, mayEdit: true);
        $this->actingInPanel($editor, $this->primary);

        Livewire::test(HelpedPage::class)
            ->mountAction('pageHelp')
            ->assertSchemaStateSet(['body' => HelpedPage::DEFAULT_HELP])
            ->assertMountedActionModalSee(self::FORM_NOTE)
            ->setActionData(['body' => "  متن تازه\nخط بعدی  "])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $help = PageHelp::query()->sole();
        $this->assertSame("متن تازه\nخط بعدی", $help->body);
        $this->assertSame($editor->getKey(), $help->updated_by);

        $this->actingInPanel($this->staff($this->primary), $this->primary);

        Livewire::test(HelpedPage::class)
            ->mountAction('pageHelp')
            ->assertMountedActionModalSee('متن تازه')
            ->assertMountedActionModalDontSee('خط اول راهنما');
    }

    public function test_the_super_admin_may_edit(): void
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);
        $this->actingInPanel($admin, $this->primary);

        Livewire::test(HelpedPage::class)
            ->mountAction('pageHelp')
            ->assertMountedActionModalSee(self::FORM_NOTE);
    }

    public function test_emptying_the_text_or_writing_the_default_goes_back_to_the_default(): void
    {
        $default = HelpedPage::DEFAULT_HELP;
        $key = 'tests.helped-page';

        PageHelp::saveText($key, 'ویرایش شده', $default, null);
        $this->assertSame('ویرایش شده', PageHelp::textFor($key, $default));

        PageHelp::saveText($key, '   ', $default, null);
        $this->assertSame($default, PageHelp::textFor($key, $default));
        $this->assertSame(0, PageHelp::query()->count());

        PageHelp::saveText($key, 'ویرایش شده', $default, null);
        PageHelp::saveText($key, $default, $default, null);
        $this->assertSame(0, PageHelp::query()->count());
    }

    public function test_the_text_is_shown_as_text_not_as_html(): void
    {
        PageHelp::saveText('tests.helped-page', '<script>alert(1)</script>', HelpedPage::DEFAULT_HELP, null);
        $this->actingInPanel($this->staff($this->primary), $this->primary);

        Livewire::test(HelpedPage::class)
            ->mountAction('pageHelp')
            ->assertMountedActionModalSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false)
            ->assertMountedActionModalDontSee('<script>alert(1)</script>', escape: false);
    }

    public function test_the_text_is_one_for_every_tenant_but_the_permission_counts_per_tenant(): void
    {
        $user = $this->staff($this->primary, mayEdit: true);
        $this->makeStaff($user, $this->branch);
        PageHelp::saveText('tests.helped-page', 'متن همه‌ی سازمان', HelpedPage::DEFAULT_HELP, null);

        $this->actingInPanel($user, $this->branch);

        Livewire::test(HelpedPage::class)
            ->mountAction('pageHelp')
            ->assertMountedActionModalSee('متن همه‌ی سازمان')
            ->assertMountedActionModalDontSee(self::FORM_NOTE);
    }

    public function test_the_role_editor_gives_the_permission(): void
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);
        $this->actingInPanel($admin, $this->primary);
        $role = $this->roleWithPermissions('help_editor', []);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['custom_permissions_tab' => [PageHelp::EDIT_PERMISSION]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($role->fresh()->hasPermissionTo(PageHelp::EDIT_PERMISSION));
    }

    public function test_the_permission_is_listed_for_roles_and_org_admin_has_it(): void
    {
        $this->assertArrayHasKey(PageHelp::EDIT_PERMISSION, FilamentShield::getCustomPermissions());
        $this->assertContains(PageHelp::EDIT_PERMISSION, app(DefaultRoles::class)->permissionsFor(DefaultRoles::ORG_ADMIN));
        $this->assertNotContains(PageHelp::EDIT_PERMISSION, app(DefaultRoles::class)->permissionsFor(DefaultRoles::TENANT_MANAGER));
    }
}
