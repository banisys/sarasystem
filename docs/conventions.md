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
- Admin forms use server-side validation and Persian field labels/messages. Keep native browser validation disabled (`novalidate`) through the panel defaults; custom raw HTML input forms in the panel must set it too. Do not remove server validation or enable browser bubbles on new forms ([ADR 0037](decisions/0037-admin-server-side-validation.md)).

## Patterns we avoid
_(TODO: things that look tempting but we decided against — link ADR if any.)_

## Creating a module ([ADR 0005](decisions/0005-modules.md))
1. `docker compose exec -u sail laravel.test php artisan module:make {Name} --inertia`, then `… composer dump-autoload`.
2. Delete the generated example controller/pages/routes you don't need; keep page names as `{Name}::Page`.
3. Models whose rows belong to a tenant: `use BelongsToTenant` and give the table a `tenant_id` FK (see Tenancy rules).
4. Admin screens go in `app/Filament/Resources` of the module. Determine whether their data depends on the current tenant and apply the tenant-independent page rule below where appropriate. Then `php artisan shield:generate --all --panel=admin --ignore-existing-policies`.
5. Tests go in `Modules/{Name}/tests` (extend `Tests\TestCase`, use `Tests\Concerns\InteractsWithTenants`) and must include a cross-tenant isolation test.
6. Document the module in `docs/architecture.md` (module list) and, if it was ported, in `docs/legacy-lms.md` → Porting status.

Business features never go in `app/`; the core in `app/` is only for platform concerns used by every module.

## Tenancy rules (see [ADR 0002](decisions/0002-tenancy-model.md))
- A model whose rows belong to one tenant **must** `use BelongsToTenant`, and its table needs a `tenant_id` foreign key to `tenants`.
- Never bypass the scope silently. `withoutTenantScope()` is only for code that has checked the user may see every tenant.
- Background work for one tenant runs inside `$tenant->execute(fn () => …)`.
- Never hard-code school-only concepts. Make them optional or configurable.
- **Tenant-independent admin pages ([ADR 0033](decisions/0033-tenant-independent-panel-pages.md)):** whenever adding or changing an admin resource or standalone page, determine whether its data depends on the current tenant. If it is installation-wide, it **must** implement [IndependentOfTenant](../app/Filament/Support/IndependentOfTenant.php). Mark the resource once to cover its list, create, edit and view pages; mark a standalone page directly. This makes the tenant selector show «مستقل از شعبه» automatically while retaining tenant switching. Verify the label on the affected pages, including navigation back to a tenant-dependent page. Do not infer independence from `isScopedToTenant = false`: tenant-owned resources using our own scopes and primary-tenant-only resources still depend on the tenant and must keep its name.

## Testing
- PHPUnit; tests live in `tests/Feature` and `tests/Unit`. Run them with `docker compose exec -u sail laravel.test php artisan test`.
- The whole suite runs in parallel with `php artisan test --parallel` (`brianium/paratest`, one process per CPU; about 5 s instead of 20 s on the dev machine). Each process gets its own database (`testing_1`, `testing_2`, …, created and migrated automatically). Single files or `--filter` runs stay sequential.
- Tests run on PostgreSQL, in the `testing` database of the `pgsql` container (`phpunit.xml`), with `RefreshDatabase`. The database is created by `docker/pgsql/create-testing-database.sql` when the volume is first initialised; on an existing volume, create it with `CREATE DATABASE testing`.
- Tenancy helpers: `Tests\Concerns\InteractsWithTenants` (`grantRole()`, `makeStaff()`). `Tests\Fixtures\TenantNote` is a minimal tenant-owned model.
- Every tenant-owned feature gets a test that another tenant's rows are invisible.

## Git
- Default branch: `main`. _(TODO: branch naming for features.)_
- Commit messages: [Conventional Commits](https://www.conventionalcommits.org/) in English, e.g. `feat(school): add classroom resource`, `docs: record tenancy ADR`, `chore: add docker compose`.
- Agents never commit on their own. At a sensible commit point, propose the message and commit only after the user approves.
