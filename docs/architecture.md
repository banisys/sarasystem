# Architecture

## Tech stack
Full list of packages and the reasoning: [ADR 0001](decisions/0001-tech-stack.md).

| Layer | Choice |
|---|---|
| Backend | Laravel 13, modular (`nwidart/laravel-modules`) |
| Admin panel (staff) | Filament 5 + plugins (Shield, Impersonate, Settings, Tags, Media Library, Excel, Apex Charts, Jalali) |
| Public site & user panel | Inertia.js + Vue 3 + Tailwind CSS |
| Database | PostgreSQL |
| Multi-tenancy | `spatie/laravel-multitenancy`, single DB, tenant = branch/unit ([ADR 0002](decisions/0002-tenancy-model.md)) |
| AI dev tooling | Laravel Boost |

## Applications / surfaces
| Surface | Who | Built with | URL |
|---|---|---|---|
| Admin panel | Head office, school management, staff | Filament | `/cpadmin` (same prefix as LMS) |
| User panel | Students, parents, teachers, supporters | Inertia + Vue | _(TBD)_ |
| Public site | Visitors (school info, registration, blog) | Inertia + Vue | `/` |

### Admin panel (Filament)
- Provider: [app/Providers/Filament/AdminPanelProvider.php](../app/Providers/Filament/AdminPanelProvider.php). The panel id is `admin`, the path is `cpadmin`, the brand comes from `APP_NAME` and the primary color is Violet.
- **Look and feel** (ported from LMS):
  - Font **Shabnam** (Light), self-hosted in `resources/fonts`, set with `->font('Shabnam', provider: LocalFontProvider::class)`.
  - Custom theme [resources/css/filament/admin/theme.css](../resources/css/filament/admin/theme.css) (`->viteTheme()`): topography watermark, white sidebar, toolbar spacing, filter dropdown above the sidebar.
  - Frosted-glass dark mode in [glass.css](../resources/css/filament/admin/glass.css), switched on by one `@import` line.
  - Colors violet / rose / emerald / amber, initials avatar ([InitialsAvatarProvider](../app/Filament/Support/InitialsAvatarProvider.php)), SPA mode, collapsible sidebar, no global search in the top bar (the sidebar menu search replaces it).
- **Render hooks** ([resources/views/filament/admin](../resources/views/filament/admin)): a sidebar menu search with history and collapse-all, remembered open state of collapsible filters, a second horizontal scrollbar above wide tables, and a tenant menu that keeps the current page.
- **Switching tenant keeps the page:** the tenant menu links are pointed (in the browser, just before they are followed) at `/cpadmin/{tenant}/switch-tenant?page=<path>`. [SwitchPanelTenantController](../app/Http/Controllers/SwitchPanelTenantController.php) opens the same page in the new tenant. If that page needs a record (edit/view) or the user can't open it there, it drops path segments until it finds a page they can open, ending at the dashboard.
- **Panel-wide component defaults** (`AdminPanelProvider::configureComponentDefaults()`), which a component's own calls override:
  - Tables: no search box (look-ups are filters; text look-ups use [TextFilter](../app/Filament/Support/TextFilter.php), which reads Persian digits as Latin; a table can opt back in with `->searchable()`), filters in a dropdown, 25 rows per page, newest first, every column toggleable.
  - Every table is a [PanelTable](../app/Filament/Support/PanelTable.php) (bound in place of Filament's `Table`): row actions are one dropdown (⋮) in the first column (ungrouped actions are wrapped in an `ActionGroup` automatically), and clicking a row does nothing, so cell text can be selected and copied. Resources just pass their actions to `->recordActions([...])` as usual. See [ADR 0006](decisions/0006-panel-table-behavior.md).
  - Numeric inputs are LTR with thousands separators.
  - Searchable selects preload and show 10 options.
- **Panel tenancy:** URLs are `/cpadmin/{tenant-slug}/…` and Filament shows a tenant switcher. See [Tenancy](#tenancy).
- Registered plugins: Shield (roles & permissions UI) and ApexCharts. The other Filament packages are used per component and need no panel registration:
  - Jalali: `->jalali()` on DatePicker/DateTimePicker, `->jalaliDate()` on TextColumn/TextEntry
  - Spatie media/tags/settings form fields
  - Excel export actions
  - Impersonate action
- **Access:** super admins (`super_admin` role, [ADR 0007](decisions/0007-super-admin-role.md)), plus users who hold Shield's `panel_user` role in at least one tenant. They see only those tenants (`User::getTenants()`/`canAccessTenant()`). Shield's `HasPanelShield` trait is deliberately **not** used, because it gives `panel_user` to every new user, and students and parents must not reach the admin panel. Shield's own super-admin feature is disabled in `config/filament-shield.php`, because its `hasRole()` check only sees the current tenant. Our `super_admin` role counts in any tenant instead.
- **Roles UI (Shield):** the resource is **published** to [app/Filament/Resources/Roles](../app/Filament/Resources/Roles/RoleResource.php) so that we can customize it. The plugin is configured with `->centralApp()->scopeToTenant(false)`. It lists all roles, and the "team" field makes a role global (empty) or specific to one tenant.
  - Roles have a Persian `label` (`roles.label`). `name` stays the technical key used in code. Use `Role::display_name` (label, or the headline of the name) everywhere in the UI.
  - ⚠️ The published `CreateRole`/`EditRole` pages treat **every form key not in their exclusion list as a permission name**. A new role attribute must be added to both lists (`['name', 'label', 'guard_name', 'select_all', …]` and `Arr::only(...)`), or it would be saved as a permission. Role definitions are shared, so the page is **super admin only** ([RolePolicy](../app/Policies/RolePolicy.php), excluded from Shield generation).
- **Role assignment rules** ([ADR 0003](decisions/0003-role-assignment-rules.md), [RoleAssignment](../app/Authorization/RoleAssignment.php)):
  - You can only assign roles whose permissions are a subset of your own in the tenant.
  - You can only manage users all of whose roles (in any tenant) you could assign.
  - You can't change your own roles.
  - Enforced on the server by the role options, a validation rule, saving and `UserResource::canEdit`.
- **Resources:**
  | Resource | Scope | Who | Notes |
  |---|---|---|---|
  | [Tenants](../app/Filament/Resources/Tenants/TenantResource.php) (`/cpadmin/{tenant}/tenants`) | installation-wide, not tenant-scoped | super admin only ([TenantPolicy](../app/Policies/TenantPolicy.php) denies everyone, `Gate::before` lets the super admin through) | Excluded from Shield generation. Tenants are deactivated, never deleted. One tenant is **primary** (star column, «تعیین به‌عنوان اصلی»; it can't be deactivated), and «صفحه‌ی خانه» opens a tenant's public page ([ADR 0009](decisions/0009-tenant-home-pages.md)). Slugs may not clash with routes or page paths (`Tenant::reservedSlugs()`). The labels come from `config('sarasystem.tenant_label')`. |
  | [Users](../app/Filament/Resources/Users/UserResource.php) (`/cpadmin/{tenant}/users`) | members of the current tenant (`User::inTenant()`) | `ViewAny/Create/Update:User` permissions (Shield `UserPolicy`) | Identity fields (name, email, password) are global; the roles checkbox list edits roles **in the current tenant only** (`syncWithPivotValues` with `tenant_id`). The list has a «تعداد شعبه‌ها» column (`User::withTenantsCount()`, sortable, tooltip with the tenant names), and the edit page has a «عضویت در شعبه‌ها» section listing every tenant of the user with their roles there (`User::memberships()`). Extra actions: «افزودن کاربر موجود» (gives roles here to an existing account instead of creating a duplicate) and «حذف از شعبه» (detaches only this tenant's roles). Super admin is the `super_admin` role in the same checkbox list; only super admins see it, and they can't remove it from themselves. Only a super admin can edit a super admin. Who can assign/edit what follows ADR 0003. |
  | [Workflows](../app/Filament/Resources/Workflows/WorkflowResource.php) «کارتابل → فرایندها» (`/cpadmin/{tenant}/workflows`) | workflows of the current tenant (`BelongsToTenant`) | `*:Workflow` permissions (Shield `WorkflowPolicy`) | The workflow designer: name, kind of record (only kinds whose `WorkflowSubject::availableIn()` allows the tenant), active flag and a repeater of steps (tenant, role or person, allowed actions). See [Workflows](#workflows-adr-0016). |
  | [Cartable](../app/Filament/Resources/WorkflowCases/WorkflowCaseResource.php) «کارتابل → کارتابل من» (`/cpadmin/{tenant}/cartable`) | cases assigned to the user in the current tenant, whichever tenant owns them (`WorkflowCase::assignedTo()`) | every staff member ([WorkflowCasePolicy](../app/Policies/WorkflowCasePolicy.php); excluded from Shield) | Badge with the count. The case page shows the step, the subject's details and the history, with the step's actions (approve / return / reject / edit / claim / release / note, plus the subject's own). |
- **Notifications:** Filament database notifications (`->databaseNotifications()`, the bell in the top bar, `notifications` table). Workflow cases notify their next assignees; sent with `notifyNow()`, so no queue worker is needed.
- **Pages:** [AccessMap](../app/Filament/Pages/AccessMap.php) «نقشه‌ی دسترسی» (`/cpadmin/{tenant}/access-map`, super admin only): who holds which role in which tenant, across all tenants. It has three views: tenant cards (roles → users, each user links to their edit page in that tenant, with a tooltip listing all their memberships), a user × tenant matrix (first 200 rows), and per role (permissions, holders per tenant). Filters: tenant, role (keeps the holders' other roles), name / mobile / national code. `panel_user` appears as a "panel" icon next to the user, not as a role group.
- Shield policies live in `app/Policies`. `FilamentShield::enforcePolicies()` in `AppServiceProvider` registers the ones Laravel can't discover. After adding resources, run `php artisan shield:generate --all --panel=admin --ignore-existing-policies`. The flag keeps hand-written policies such as `TenantPolicy` intact. Permission keys look like `ViewAny:User`, `Create:User`.
- Filament plugin assets in `public/{css,js,fonts}/…` are **not committed**. `composer install` and `composer update` republish them through `filament:upgrade` (post-autoload-dump), and a deploy that skips composer scripts must run `php artisan filament:assets` itself.

### Public site & user panel (Inertia)
- Root view: [resources/views/app.blade.php](../resources/views/app.blade.php). It sets `dir="rtl"` for the `fa` locale and includes `@routes` (Ziggy).
- Entry: [resources/js/app.js](../resources/js/app.js). Pages live in `resources/js/Pages/**`, are resolved by name and are lazy-loaded. The `ZiggyVue` plugin provides `route()`.
- Shared props: [HandleInertiaRequests](../app/Http/Middleware/HandleInertiaRequests.php) shares `auth.user` (`id`, `name`, `email`). It is appended to the `web` middleware group in `bootstrap/app.php`.
- Vite ([vite.config.js](../vite.config.js)): the Vue plugin, Tailwind 4 and the `ziggy-js` alias pointing to `vendor/tightenco/ziggy`. The dev server listens on `0.0.0.0:VITE_PORT` (5174) inside the container.
- Font: **Shabnam**, the same self-hosted font as the admin panel (`@font-face` + Tailwind `--font-sans` in `resources/css/app.css`).
- **Public site** ([ADR 0009](decisions/0009-tenant-home-pages.md), [ADR 0010](decisions/0010-site-pages.md), [PublicSiteController](../app/Http/Controllers/PublicSiteController.php), [app/PublicSite](../app/PublicSite)): `/` and a fallback route serve every public URL. `/{slug}/...` is a non-primary tenant's site, everything else the primary tenant's. Modules answer through tagged `PageResolver`s (the `Site` module's pages); otherwise `/` is the default home page ([Tenants/Home.vue](../resources/js/Pages/Tenants/Home.vue), or `Welcome` with no primary tenant) and anything else 404. Public pages use [PublicLayout.vue](../resources/js/Layouts/PublicLayout.vue) (static header/footer, `site` prop from `PublicSite::layoutProps()`; header links from modules in `site.navigation`, [NavMenu.vue](../resources/js/Components/Public/NavMenu.vue)).
- Jalali dates in Vue forms ([ADR 0015](decisions/0015-jalali-date-input.md)): use [JalaliDateInput](../resources/js/Components/JalaliDateInput.vue) (`v-model` = `1370/01/05`, typed or picked from the calendar; no date library, built on `Intl`). The server validates and converts with [JalaliDate](../app/Rules/JalaliDate.php) (Persian digits allowed).

### Authentication ([ADR 0004](decisions/0004-authentication.md))
- **One login page** at `/login` ([Auth/Login.vue](../resources/js/Pages/Auth/Login.vue)) for everyone. The Filament panel has no login of its own (`->login()` removed): guests hitting `/cpadmin/...` are redirected to `/login` and then back to the intended URL. Panel logout still works through Filament's logout route.
- **Identifier:** mobile, national code or email in one field (`User::scopeWhereLoginIdentifier`, [Identifiers](../app/Support/Identifiers.php)). Mutators normalize them on save: mobile to `09xxxxxxxxx`, Latin digits, lowercase email. `users.mobile` is indexed but not unique; `national_code` and `email` are unique and nullable.
- **Password:** [LoginRequest](../app/Http/Requests/Auth/LoginRequest.php). **SMS code:** `POST /login/code/send` ([SendLoginCodeController](../app/Http/Controllers/Auth/SendLoginCodeController.php)) and then `POST /login/code` ([OtpLoginRequest](../app/Http/Requests/Auth/OtpLoginRequest.php)). The codes are handled by [OneTimePassword](../app/Auth/OneTimePassword.php): hashed in the cache, 2 min TTL, 5 tries, 60 s resend. A code is tied to an owner (a user, or a string such as `guest` for visitors without an account, e.g. the «همکاری با ما» form), a purpose and a mobile. A code login sets `mobile_verified_at`.
- Shared logic is in [ResolvesLoginUser](../app/Http/Requests/Auth/ResolvesLoginUser.php): rate limiting (5 failures per identifier + IP) and resolving the identifier to exactly one user (shared mobile → error).
- **SMS:** `App\Sms\SmsSender`, bound in `AppServiceProvider` from `config('sms.driver')` (`SMS_DRIVER`). Only `log` exists, and it writes to `storage/logs/laravel.log`. Tests use `Tests\Fakes\FakeSmsSender`.
- **After login:** `redirect()->intended(route('dashboard'))`. `/dashboard` and other tenant-bound user routes use [EnsureTenantSelected](../app/Http/Middleware/EnsureTenantSelected.php), which redirects to `/tenants/select` ([Tenants/Select.vue](../resources/js/Pages/Tenants/Select.vue)) when no tenant is current. Super admins can pick any active tenant.
- **Profile** (`/profile`, [ProfileController](../app/Http/Controllers/ProfileController.php), [Profile/Edit.vue](../resources/js/Pages/Profile/Edit.vue)): the one account page for everyone. Staff reach it from the Filament user menu («حساب کاربری»).
  - Users can change their email, mobile and password. Name and national code are official data and are changed by staff only.
  - **Mobile:** the new number is saved only after a code sent *to it* is confirmed (`OneTimePassword::PURPOSE_CONFIRM_MOBILE`; codes are tied to user + purpose + mobile).
  - **Password:** the current password is required, except within 15 minutes of an SMS-code login ([RecentCodeLogin](../app/Auth/RecentCodeLogin.php)). That is the forgotten-password path.
  - Authenticated web routes use `auth.session`, so a password change logs out the user's other sessions.
- **Impersonation** ([ADR 0008](decisions/0008-impersonation.md), [Impersonation](../app/Auth/Impersonation.php)): a super admin can log in as another (non-super-admin) user from the user edit page («ورود با این کاربر»). A bar on every page («بازگشت به حساب خودم», [panel view](../resources/views/filament/admin/impersonation-banner.blade.php) and `AppLayout.vue`) posts to `/impersonation/leave`, which logs them back in and returns to the edit page.
- **User-panel layout:** [AppLayout.vue](../resources/js/Layouts/AppLayout.vue) (app name, current tenant, switch link, admin-panel link if `auth.canAccessAdmin`, logout). Shared Inertia props: `appName`, `auth.{user,impersonator,canAccessAdmin}`, `tenant.{current,available,label}`, `navigation` (module links in the header), `flash.status`.
- `config/inertia.php` is published only to point page tests at `resources/js/Pages` (Inertia 3 defaults to `js/pages`).

## Directory map
Laravel 13 skeleton plus the stack setup (2026-10-23). It will be updated as modules are added.
```
app/                  core application code (outside modules)
  Workflow/           workflow engine, step/assignee/action enums, WorkflowSubject(s) (ADR 0016)
  Http/Middleware/    HandleInertiaRequests
  Models/User.php     HasRoles (Shield/Spatie permission), FilamentUser
  Policies/           Shield-generated policies
  Providers/Filament/ AdminPanelProvider
bootstrap/            app bootstrap, providers list
config/               configuration (incl. filament-shield, permission, settings, modules, multitenancy)
database/             core migrations (users, cache, jobs, permission, media, tags, settings), factories, seeders
docs/                 project docs for humans and agents (this folder)
Modules/              business modules (ADR 0005): Site, Exam, Employee; merged into composer via wikimedia/composer-merge-plugin
stubs/nwidart-stubs/  our module generator templates (Inertia, tenant routes, no API/Blade)
public/               web root
resources/js/Pages/   Inertia Vue pages
resources/views/      app.blade.php (Inertia root)
routes/               web / console routes
tests/                Feature + Unit tests (PHPUnit); TestCase calls withoutVite()
.claude/skills/       Laravel Boost skills (generated)
.mcp.json, boost.json Laravel Boost MCP + config
```

## Modules
Decided in [ADR 0005](decisions/0005-modules.md): the **platform core lives in `app/`** and **business areas live in `Modules/{Name}`** (nwidart). The core never depends on a module.

| Concern | How modules plug in |
|---|---|
| Admin panel | `Modules/{Name}/app/Filament/{Resources,Pages,Widgets}`, auto-discovered for enabled modules (`AdminPanelProvider::discoverModules()`). |
| User panel | Pages in `Modules/{Name}/resources/js/Pages`, rendered as `Inertia::render('{Name}::Page')`. Shared layout: `@/Layouts/AppLayout.vue`; header links with `UserPanel::navigationUsing()` ([app/UserPanel](../app/UserPanel/UserPanel.php), shared prop `navigation`; ADR 0013). |
| Public site | Tag a `App\PublicSite\PageResolver` with `PublicSite::RESOLVER_TAG`; reserve URL segments with `Tenant::reserveSlugsUsing()` (ADR 0010); header links with `PublicSite::navigationUsing()` (ADR 0012). |
| Tenants list | Row actions with `TenantResource::addRecordActionsUsing()` (ADR 0012). |
| Workflows | Make a module's records go through designed workflows: `WorkflowSubjects::register(new FooSubject)` with a `App\Workflow\WorkflowSubject` (ADR 0016). |
| Routes | `Modules/{Name}/routes/web.php` with `['auth', 'auth.session', 'tenant']` (`tenant` = `EnsureTenantSelected`). |
| Data | Module migrations are auto-discovered; tenant-owned models use `BelongsToTenant`. |
| Tests | `Modules/{Name}/tests`, run by the `Modules` PHPUnit suite. |
| Enable/disable | `modules_statuses.json`. |

**Module list:**
| Module | What | Notes |
|---|---|---|
| [Site](../Modules/Site) | Public pages of each tenant's site (page builder); every tenant has a home page (`/`) created with it; page content is made of widgets; site menus | `site_pages` (`content` jsonb), `Page` model, `PageResource` («وب‌سایت → صفحه‌ها»; widgets in the page builder «صفحه‌ساز» `pages/{id}/builder`, from the row action), `SitePageResolver`, `Site::Page`; widget types in `Modules/Site/app/Widgets` + `resources/js/Widgets` (HTML so far); menus `site_menus` / `site_menu_items`, `MenuResource` («وب‌سایت → منوها», item tree `menus/{id}/items`), each tenant's header menu `site_tenant_menus` (row action «منوی سایت» in the tenants list). [ADR 0010](decisions/0010-site-pages.md), [ADR 0011](decisions/0011-site-widgets.md), [ADR 0012](decisions/0012-site-menus.md). |
| [Exam](../Modules/Exam) | Online exams of each tenant: question banks, choice / written questions, exams drawing questions from banks, participants (users and roles), attempts with autosave and time-out, automatic + manual grading, results | `exam_question_banks`, `exam_questions` (`options` jsonb), `exams`, `exam_sections`, `exam_participant_users/roles`, `exam_attempts`, `exam_attempt_answers` (copy of each served question). Rules in [ExamTaking](../Modules/Exam/app/Services/ExamTaking.php). Admin group «آزمون» (exams, results + grading, questions, banks); user panel `/exams` (`Exam::Index`, `Exam::Show`). [ADR 0013](decisions/0013-exams.md). |
| [Employee](../Modules/Employee) | Employees and hiring: the public «همکاری با ما» form at `/careers` (primary tenant only), the applicants list, hiring as a workflow per job position (ADR 0016), and the employees of each tenant | `employee_job_positions` (`JobPosition`, optional `workflow_id`), `employee_job_applications` (`JobApplication`, education / work history jsonb, résumé in medialibrary on the private disk, one open application per national code), `employees` (`Employee`: user, job title, personnel code, employment type, status, dates; one per tenant and user). The mobile is confirmed by an SMS code before saving ([ApplicantMobile](../Modules/Employee/app/Support/ApplicantMobile.php)). Public page `Employee::Careers` ([CareersController](../Modules/Employee/app/Http/Controllers/CareersController.php), [StoreJobApplicationRequest](../Modules/Employee/app/Http/Requests/StoreJobApplicationRequest.php)); a new application starts its position's workflow. Workflow subject [JobApplicationSubject](../Modules/Employee/app/Workflow/JobApplicationSubject.php); the last approval runs [HireApplicant](../Modules/Employee/app/Services/HireApplicant.php) (user, roles, employee). Admin group «همکاری با ما» (applicants: list with current step, view with workflow history, «شروع فرایند استخدام», résumé download; job positions with their workflow), only in the primary tenant's panel; «کارکنان → کارمندان» in every tenant. [ADR 0014](decisions/0014-job-applications.md), [ADR 0016](decisions/0016-workflows.md). |

Further modules will be derived from the domain map in [legacy-lms.md](legacy-lms.md#mapping-to-sarasystem-domains) as parts are ported.

## Tenancy
Decided in [ADR 0002](decisions/0002-tenancy-model.md): a tenant is a branch/unit (school, branch or department), all tenants share one database, users are global, roles are per tenant, and the tenant is chosen after login.

**Pieces:**
| Piece | Where | What it does |
|---|---|---|
| `tenants` table, `Tenant` model | [app/Models/Tenant.php](../app/Models/Tenant.php) | Extends spatie's Tenant (`makeCurrent()`, `current()`, `execute()`). Columns: `name`, `slug` (URL), `is_active`, `is_primary` (at most one; its home page is `/`). Head office is the seeded tenant `head-office`; locally the seeder also creates the super admin (`admin@sarasystem.test`, `super_admin` + `panel_user` there) and runs [DemoSeeder](../database/seeders/DemoSeeder.php): branches north / center / west, roles (کارمند ستاد, مدیر شعبه, معلم, پشتیبان, ناظر, والد, دانش‌آموز, کاربر) and users like `teacher1.north@sarasystem.test`. All local users have the password `password`. |
| `BelongsToTenant` trait | [app/Models/Concerns/BelongsToTenant.php](../app/Models/Concerns/BelongsToTenant.php) | **Every tenant-owned model uses it** (table needs `tenant_id`). It adds `TenantScope` and fills `tenant_id` on create (throws `NoCurrentTenant` if there is none), plus the `tenant()` relation that Filament uses for ownership. |
| `TenantScope` | [app/Models/Scopes/TenantScope.php](../app/Models/Scopes/TenantScope.php) | Filters by the current tenant. It **fails closed**: with no current tenant it matches nothing. Cross-tenant code uses `Model::withoutTenantScope()` (after its own authorization check) or `$tenant->execute(fn () => …)`. |
| Permission teams | `config/permission.php` | `teams = true`, `team_foreign_key = tenant_id`, role model `App\Models\Role`. Every role *assignment* is per tenant (`model_has_roles.tenant_id`). A role *definition* is global (`roles.tenant_id` NULL) or tenant-specific. |
| `SwitchPermissionsTeamTask` | [app/Multitenancy/SwitchPermissionsTeamTask.php](../app/Multitenancy/SwitchPermissionsTeamTask.php) | Switch task: `makeCurrent()` sets the permission team id to the tenant id, and forgetting the tenant clears it. |
| Membership | `User::tenants()` / `Tenant::users()` | No separate table: a user belongs to a tenant when they hold any role there. `User::staffTenants()` is the subset where they hold `panel_user`. |
| Super admin | Global `super_admin` role + `User::isSuperAdmin()` + `Gate::before` in `AppServiceProvider` | Holding the role in **any** tenant makes the user installation-wide: they pass every check and see all active tenants. Only super admins can assign it. `super_admin` and `panel_user` are system roles (`Role::isSystemRole()`): they can't be deleted or renamed. See [ADR 0007](decisions/0007-super-admin-role.md). |
| Admin panel | `->tenant(Tenant::class, slugAttribute: 'slug')` + [MakeFilamentTenantCurrent](../app/Http/Middleware/MakeFilamentTenantCurrent.php) (persistent tenant middleware) | Filament picks the tenant from the URL, and the middleware makes it spatie's current tenant, which also switches the permission team. |
| Web / Inertia | [ResolveCurrentTenant](../app/Http/Middleware/ResolveCurrentTenant.php) (web group, runs before route-model binding: `bootstrap/app.php`) + `POST /tenants/{slug}/switch` | The tenant id is kept in the session, and tenant-owned route parameters (`{exam}`) are bound inside the current tenant. It is auto-selected when the user has exactly one tenant, and dropped if the user no longer belongs to it. Shared Inertia props: `tenant.current`, `tenant.available`. |

spatie-multitenancy's `tenant_finder` is **not used**: it runs before the session exists, so the middlewares above resolve the tenant instead. Queued jobs are tenant-aware by default (spatie config).

**Tenant label:** `config/sarasystem.php` (`TENANT_LABEL`, `TENANT_PLURAL_LABEL` in `.env`, default «شعبه»). Use it in the UI wherever the word for tenant appears; never hard-code «مدرسه» or «شعبه».

Web-side tenant picking after login is described under [Authentication](#authentication-adr-0004).

## Workflows ([ADR 0016](decisions/0016-workflows.md))
Approval workflows («فرایند») and the cartable («کارتابل») are platform core; modules plug in the kinds of records that go through them.

| Piece | Where | What it does |
|---|---|---|
| `Workflow` | [app/Models/Workflow.php](../app/Models/Workflow.php) | Tenant-owned. `subject_type` (morph class of the records), active flag, `steps` jsonb: name, tenant, assignee (role in that tenant, or one person), allowed extra actions ([StepAction](../app/Workflow/StepAction.php): reject / return / edit). |
| `WorkflowCase` | [app/Models/WorkflowCase.php](../app/Models/WorkflowCase.php) | One record going through a workflow. Owned by the subject's tenant, keeps a copy of the steps from its start, the current step and its assignee in columns, status ([CaseStatus](../app/Workflow/CaseStatus.php)), claim. `assignedTo($user, $tenant)` is the cartable query (across owner tenants); `subject` is read without the tenant scope. |
| `WorkflowCaseAction` | [app/Models/WorkflowCaseAction.php](../app/Models/WorkflowCaseAction.php) | History line ([CaseActionType](../app/Workflow/CaseActionType.php)): who, which step, comment, old/new values of edits. No `tenant_id`; read through its case. |
| `WorkflowEngine` | [app/Workflow/WorkflowEngine.php](../app/Workflow/WorkflowEngine.php) | `start`, `approve`, `reject`, `returnTo`, `edit`, `comment`, `claim`, `release`, `recordSubjectUpdate`. Locks the case, checks the actor is the assignee and the case is still at the step they saw, logs, calls the subject's hooks, notifies the next assignees. Refusals throw [WorkflowActionDenied](../app/Workflow/WorkflowActionDenied.php) (shown to the user, transaction undone). |
| `WorkflowSubject` / `WorkflowSubjects` | [app/Workflow](../app/Workflow) | What a module provides per kind of record: label, title, details (infolist), extra actions, edit form, completion fields, hooks `moved` / `completed` / `rejected`, `availableIn($tenant)`. Registered in the module's service provider. |
| Admin screens | `app/Filament/Resources/{Workflows,WorkflowCases}`, [CaseHistory](../app/Filament/Workflow/CaseHistory.php) | Designer, cartable and the history entry (also used on a subject's own page). |

## Data model
_(TODO: main entities and their relations.)_

## Data flow
_(TODO: how a typical request/action moves through the system.)_

## External services & integrations
_(TODO: e.g. online classes (legacy: Adobe Connect), SMS, payment gateway.)_

## Local development
[docker-compose.yml](../docker-compose.yml) (Sail-compatible):

| Service | Image | Host port | Notes |
|---|---|---|---|
| `laravel.test` | `sail-8.5/app` (PHP 8.5, node, composer, pdo_pgsql) | `APP_PORT` 8000, `VITE_PORT` 5174 | Image is reused from `../LMS` (built locally) |
| `pgsql` | `postgres:18-alpine` | `FORWARD_DB_PORT` 5432 | DB `sarasystem`, user `sail` by default; data in volume `sail-pgsql` |
| `adminer` | `adminer:latest` | `FORWARD_ADMINER_PORT` 8081 | DB web UI (phpMyAdmin is MySQL-only). Log in with system *PostgreSQL*, server `pgsql`, user/password from `.env` |

`.env` (from `.env.example`) sets `DB_CONNECTION=pgsql` with `DB_HOST=pgsql`, `APP_LOCALE=fa`, `APP_FAKER_LOCALE=fa_IR` and the compose ports. The app timezone stays UTC; Jalali/Tehran display is a presentation concern.

The ports are chosen so they don't clash with LMS (80, 5173, 3306, 8080; Adminer uses 8081), and both projects can run together. Defaults can be overridden in `.env`.

## Deployment
_(TODO: environments, how it's deployed.)_
