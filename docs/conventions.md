# Conventions

## Working environment
_(Carried over from how the user works on LMS; confirm once the SaraSystem stack is decided.)_
- Development runs in Docker (Laravel Sail for LMS); run project commands inside the container.
- Containers have **no internet access** by default. If a package needs installing, give the user the exact command and let them run it.
  - The user's VPN is `sshuttle`, which only tunnels the host's own traffic. [docker/vpn-proxy/install.sh](../docker/vpn-proxy/install.sh) gives all containers internet while the VPN runs: a tinyproxy container on the host network (`vpn-proxy`, files in `~/docker-vpn-proxy`) plus `proxies` in `~/.docker/config.json`. It is per machine (run once on each), not part of `docker-compose.yml`. The sshuttle command must exclude `172.16.0.0/12`; don't change it otherwise (`-l 0.0.0.0` broke the VPN).
  - For the same reason the project's Docker network has a fixed subnet inside `172.16.0.0/12` (`SAIL_SUBNET`, default `172.16.60.0/24`, in `docker-compose.yml`). When Docker picks one itself it can land in `192.168.x` once the 172.x ranges are taken; sshuttle then tunnels host traffic to the containers and `localhost:8000` hangs. If the subnet clashes with another network on a machine, change `SAIL_SUBNET` in `.env`.
- Fresh clone: `cp .env.example .env`, then `docker compose up -d`, `php artisan key:generate`, `php artisan migrate --seed` (in the container). Without `.env` the app runs as `production` with no key (500) and Boost's MCP does not start.
- The user pulls Docker images through the ArvanCloud mirror and then tags them with the standard name, so `docker-compose.yml` keeps standard image names. Example: `docker pull docker.arvancloud.ir/postgres:18-alpine && docker tag docker.arvancloud.ir/postgres:18-alpine postgres:18-alpine`.
- Talk to the user in Persian (Persian script); code, identifiers and these docs stay in English.
- **Laravel Boost** is installed: use its MCP tools (`search-docs`, `database-schema`, `database-query`, logs) before guessing about Laravel, Filament or Inertia APIs. Its guidelines sit at the bottom of `CLAUDE.md` and its skills in `.claude/skills/`. Our rules in `CLAUDE.md` take precedence.
  - `.mcp.json` is hand-edited to run `boost:mcp` through `docker compose exec`, because the host has no PHP. If `boost:install` rewrites it, restore it.
  - `boost.json`: `agents: [claude_code]`, `cloud: false` (we don't use Laravel Cloud). Regenerate with `php artisan boost:update`.

## Porting from LMS
Nothing is copied from `../LMS` as-is. Port only on the user's request, one named part at a time:

1. **Review** the LMS implementation of that part: models, tables (live DB, read-only), Filament resources, controllers/ViewModels, bridges and business rules. Note bugs, dead code, MySQL-specific SQL, legacy patterns and overlaps (see [legacy-lms.md](legacy-lms.md)).
2. **Summarize** for the user before writing code: what the part does, which business rules must be kept, what you plan to drop or change and why. Ask when a rule is unclear instead of guessing.
3. **Rewrite** it for this stack ([ADR 0001](decisions/0001-tech-stack.md)): clean PostgreSQL schema and naming, Filament for admin, Inertia/Vue for user-facing parts, Spatie packages instead of the legacy subsystems, and no ViewModels or legacy theme/block layer. Refactor wherever the legacy design is weak.
4. **Document** it: update the module's docs and `progress.md`. Record any real design choice as an ADR. In `legacy-lms.md`, note that the part has been ported and where it now lives.

## Code style
_(TODO: formatter/linter, naming rules, file naming.)_

## Patterns we use
- For Vue pages, reuse the shared controls and Tailwind tokens in [ADR 0094](decisions/0094-vue-design-system.md). Use logical spacing and positioning, and set explicitly left-to-right fields such as phone numbers with `dir="ltr"`.

- Define user-panel routes under `/profile` and generate links and form actions from route names ([ADR 0093](decisions/0093-user-panel-url-prefix.md)).

- Display hierarchical paths with directional chevron icons between labels, as in [Tree Select](decisions/0081-admin-tree-select.md). In admin Blade views, reuse `<x-hierarchy-path-separator />` and keep labels as separate segments so a slash in a label stays visible.

- Admin forms use server-side validation and Persian field labels/messages. Keep native browser validation disabled (`novalidate`) through the panel defaults; custom raw HTML input forms in the panel must set it too. Do not remove server validation or enable browser bubbles on new forms ([ADR 0037](decisions/0037-admin-server-side-validation.md)).

- Page help ([ADR 0065](decisions/0065-page-help.md)): to give a panel page help, `use HasPageHelp` on the page and implement `pageHelpKey()` (fixed, e.g. `contract.contract-types`) and `defaultPageHelp()` (plain text). Add a page's help when the user asks for it. Keep the text true to what the code does, and update it when the page's behaviour changes.

- Create rows with Eloquent (`create()`, `save()`), not query-builder `insert()`, in code a web request runs. The step-by-step tutorial records rows through model events and deletes them on «شروع دوباره» ([ADR 0040](decisions/0040-step-by-step-tutorial.md)). Public-site lists of tenants or other records a tutorial could make go through `HiddenRecords::exclude()`.

## Keeping the tutorial up to date ([ADR 0040](decisions/0040-step-by-step-tutorial.md))
The step-by-step tutorial («آموزش گام‌به‌گام», module `Tutorial`) is developed together with the project, not afterwards. At the end of every task:

1. **Check** whether the change adds or changes something a client would do or see: a screen, form, action, workflow, role, public page or setting. Pure refactors, fixes without visible change and internal tooling need nothing.
2. **If it does**, update the scenarios it applies to in [Modules/Tutorial/app/Scenarios](../Modules/Tutorial/app/Scenarios): add a mission (or steps to an existing one) with its state check, fix missions whose screens, labels or flow changed, and remove missions for removed features. Keep missions in the order a new client would do them.
3. If a new feature fits a scenario that doesn't exist yet (school, company), note it in `progress.md` under the tutorial's «In progress» item so it is added with that scenario.
4. Update the tutorial's tests for the changed checks, and mention the tutorial change (or that none was needed) in the task summary.

## Patterns we avoid
_(TODO: things that look tempting but we decided against — link ADR if any.)_

## Creating a module ([ADR 0005](decisions/0005-modules.md))
1. `docker compose exec -u sail laravel.test php artisan module:make {Name} --inertia`, then `… composer dump-autoload`.
2. Delete the generated example controller/pages/routes you don't need; keep page names as `{Name}::Page`.
3. Models whose rows belong to a tenant: `use BelongsToTenant` and give the table a `tenant_id` FK (see Tenancy rules).
4. Admin screens go in `app/Filament/Resources` of the module. Determine whether their data depends on the current tenant and apply the tenant-independent page rule below where appropriate. Then `php artisan shield:generate --all --panel=admin --ignore-existing-policies`, and give the default roles the new permissions with `app(DefaultRoles::class)->grant([...])` in the module's service provider ([ADR 0039](decisions/0039-default-roles.md)).
5. Tests go in `Modules/{Name}/tests` (extend `Tests\TestCase`, use `Tests\Concerns\InteractsWithTenants`) and must include a cross-tenant isolation test.
6. Keep the module’s `description` and optional `sitemap` manifest metadata current; verify its card, links and connections in «نقشهٔ سامانه». New pages/modules are discovered automatically; see [System map maintenance](system-map.md).
7. Document the module in `docs/architecture.md` (module list) and, if it was ported, in `docs/legacy-lms.md` → Porting status.

Business features never go in `app/`; the core in `app/` is only for platform concerns used by every module.

## Independent modules ([ADR 0041](decisions/0041-independent-modules-per-feature-licensing.md))
Some clients pay per feature, so an installation must work with any module disabled.
- A module uses only the core in `app/` and its own code. No `use Modules\Other\…`, no foreign keys to another module's tables, no other module's permissions, routes or pages.
- To work with another module, use a core hook (an interface or registry in `app/`, an event, `ModelRelationManagers`) or a hook owned by the module being extended. When the other module is disabled, the hook is simply not registered and nothing breaks.
- A real dependency (a module that only makes sense on top of another, like School on Class) is the exception: discuss it with the user, declare it in `module.json` (`requires`) and in `architecture.md`.
- Cross-module features (tutorial missions, default-role grants, tabs, workflow steps) check `Module::isEnabled('Name')` and are left out otherwise.
- Tests that need another module skip when it is disabled; core tests assume no module.
- **Before a module or cross-module feature is done,** disable each module it touches (`php artisan module:disable Name`) and run the remaining tests and a page load, then enable it again.

## Tenancy rules (see [ADR 0002](decisions/0002-tenancy-model.md))
- A model whose rows belong to one tenant **must** `use BelongsToTenant`, and its table needs a `tenant_id` foreign key to `tenants`.
- Never bypass the scope silently. `withoutTenantScope()` is only for code that has checked the user may see every tenant.
- Background work for one tenant runs inside `$tenant->execute(fn () => …)`.
- Never hard-code school-only concepts. Make them optional or configurable.
- **Tenant-independent admin pages ([ADR 0033](decisions/0033-tenant-independent-panel-pages.md)):** whenever adding or changing an admin resource or standalone page, determine whether its data depends on the current tenant. If it is installation-wide, it **must** implement [IndependentOfTenant](../app/Filament/Support/IndependentOfTenant.php). Mark the resource once to cover its list, create, edit and view pages; mark a standalone page directly. This makes the tenant selector show «مستقل از شعبه» automatically while retaining tenant switching. Verify the label on the affected pages, including navigation back to a tenant-dependent page. Do not infer independence from `isScopedToTenant = false`: tenant-owned resources using our own scopes and primary-tenant-only resources still depend on the tenant and must keep its name.

## Testing
- PHPUnit; tests live in `tests/Feature`, `tests/Unit` and `Modules/{Name}/tests`.
- **Which tests to run ([ADR 0059](decisions/0059-running-tests.md)):**
  - For small appearance-only changes, check the affected UI once in the browser. Run a build when needed to see the change or a targeted test if it covers the result.
  - For behavior changes, run only the affected tests while working. Use `php artisan test --compact Modules/{Name}/tests`, a file, or `--filter`. If the change affects a tutorial mission, add `Modules/Tutorial/tests`.
  - Run the whole suite once at the end for changes to shared infrastructure (such as test helpers, config or core hooks) or behavior across modules. A commit alone does not require the whole suite.
- The whole suite runs in parallel with `php artisan test --parallel --processes=8` (`brianium/paratest`). More processes gave no real gain on the dev machine. Each process gets its own database (`testing_test_1`, `testing_test_2`, …, created and migrated automatically). Single files or `--filter` runs stay sequential.
- Before adding expensive work to a `setUp()`, check its cost: it is paid once per test. A slow helper or service used in `setUp()` is usually better fixed at its source.
- Tests run on PostgreSQL, in the `testing` database of the `pgsql` container (`phpunit.xml`), with `RefreshDatabase`. The database is created by `docker/pgsql/create-testing-database.sql` when the volume is first initialised; on an existing volume, create it with `CREATE DATABASE testing`.
- Tenancy helpers: `Tests\Concerns\InteractsWithTenants` (`grantRole()`, `makeStaff()`). `Tests\Fixtures\TenantNote` is a minimal tenant-owned model.
- Every tenant-owned feature gets a test that another tenant's rows are invisible.

## Git
- Default branch: `main`. _(TODO: branch naming for features.)_
- Commit messages: [Conventional Commits](https://www.conventionalcommits.org/) in English, e.g. `feat(school): add classroom resource`, `docs: record tenancy ADR`, `chore: add docker compose`.
- Agents leave small edits uncommitted until related changes form a meaningful, verified batch or the user asks for a commit. They commit substantial finished work and ready batches on `main` without asking and tell the user what they committed. Unrelated edits stay separate. Agents don't push unless asked.
