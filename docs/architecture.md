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
- **Panel tenancy:** URLs are `/cpadmin/{tenant-slug}/…` and Filament shows a tenant switcher. See [Tenancy](#tenancy).
- Registered plugins: Shield (roles & permissions UI) and ApexCharts. The other Filament packages are used per component and need no panel registration:
  - Jalali: `->jalali()` on DatePicker/DateTimePicker, `->jalaliDate()` on TextColumn/TextEntry
  - Spatie media/tags/settings form fields
  - Excel export actions
  - Impersonate action
- **Access:** super admins (`users.is_super_admin`), plus users who hold Shield's `panel_user` role in at least one tenant. They see only those tenants (`User::getTenants()`/`canAccessTenant()`). Shield's `HasPanelShield` trait is deliberately **not** used, because it gives `panel_user` to every new user, and students and parents must not reach the admin panel. Shield's own `super_admin` role is disabled in `config/filament-shield.php`, because a role can't be installation-wide with teams.
- **Roles UI (Shield):** `->centralApp()->scopeToTenant(false)`. It lists all roles, and the "team" field makes a role global (empty) or specific to one tenant. Assigning roles to users must pass the current tenant id (see the Shield README, "With Tenancy").
- Shield policies live in `app/Policies`. `FilamentShield::enforcePolicies()` in `AppServiceProvider` registers the ones Laravel can't discover, such as `RolePolicy` for Spatie's `Role`. After adding resources, run `php artisan shield:generate --all --panel=admin`.
- Filament plugin assets in `public/{css,js,fonts}/…` are **not committed**. `composer install` and `composer update` republish them through `filament:upgrade` (post-autoload-dump), and a deploy that skips composer scripts must run `php artisan filament:assets` itself.

### Public site & user panel (Inertia)
- Root view: [resources/views/app.blade.php](../resources/views/app.blade.php). It sets `dir="rtl"` for the `fa` locale and includes `@routes` (Ziggy).
- Entry: [resources/js/app.js](../resources/js/app.js). Pages live in `resources/js/Pages/**`, are resolved by name and are lazy-loaded. The `ZiggyVue` plugin provides `route()`.
- Shared props: [HandleInertiaRequests](../app/Http/Middleware/HandleInertiaRequests.php) shares `auth.user` (`id`, `name`, `email`). It is appended to the `web` middleware group in `bootstrap/app.php`.
- Vite ([vite.config.js](../vite.config.js)): the Vue plugin, Tailwind 4 and the `ziggy-js` alias pointing to `vendor/tightenco/ziggy`. The dev server listens on `0.0.0.0:VITE_PORT` (5174) inside the container.
- Not decided yet: the Persian font (self-hosted Vazirmatn is the plan), the Jalali date picker for Vue, and how modules contribute their own Inertia pages.

## Directory map
Laravel 13 skeleton plus the stack setup (2026-10-23). It will be updated as modules are added.
```
app/                  core application code (outside modules)
  Http/Middleware/    HandleInertiaRequests
  Models/User.php     HasRoles (Shield/Spatie permission), FilamentUser
  Policies/           Shield-generated policies
  Providers/Filament/ AdminPanelProvider
bootstrap/            app bootstrap, providers list
config/               configuration (incl. filament-shield, permission, settings, modules, multitenancy)
database/             core migrations (users, cache, jobs, permission, media, tags, settings), factories, seeders
docs/                 project docs for humans and agents (this folder)
Modules/              nwidart modules (none yet); merged into composer via wikimedia/composer-merge-plugin
public/               web root
resources/js/Pages/   Inertia Vue pages
resources/views/      app.blade.php (Inertia root)
routes/               web / console routes
tests/                Feature + Unit tests (PHPUnit); TestCase calls withoutVite()
.claude/skills/       Laravel Boost skills (generated)
.mcp.json, boost.json Laravel Boost MCP + config
```

## Modules
_(TODO: module list and boundaries — to be derived from the domain map in [legacy-lms.md](legacy-lms.md#mapping-to-sarasystem-domains).)_

## Tenancy
Decided in [ADR 0002](decisions/0002-tenancy-model.md): a tenant is a branch/unit (school, branch or department), all tenants share one database, users are global, roles are per tenant, and the tenant is chosen after login.

**Pieces:**
| Piece | Where | What it does |
|---|---|---|
| `tenants` table, `Tenant` model | [app/Models/Tenant.php](../app/Models/Tenant.php) | Extends spatie's Tenant (`makeCurrent()`, `current()`, `execute()`). Columns: `name`, `slug` (URL), `is_active`. Head office is the seeded tenant `head-office`. |
| `BelongsToTenant` trait | [app/Models/Concerns/BelongsToTenant.php](../app/Models/Concerns/BelongsToTenant.php) | **Every tenant-owned model uses it** (table needs `tenant_id`). It adds `TenantScope` and fills `tenant_id` on create (throws `NoCurrentTenant` if there is none), plus the `tenant()` relation that Filament uses for ownership. |
| `TenantScope` | [app/Models/Scopes/TenantScope.php](../app/Models/Scopes/TenantScope.php) | Filters by the current tenant. It **fails closed**: with no current tenant it matches nothing. Cross-tenant code uses `Model::withoutTenantScope()` (after its own authorization check) or `$tenant->execute(fn () => …)`. |
| Permission teams | `config/permission.php` | `teams = true`, `team_foreign_key = tenant_id`, role model `App\Models\Role`. Every role *assignment* is per tenant (`model_has_roles.tenant_id`). A role *definition* is global (`roles.tenant_id` NULL) or tenant-specific. |
| `SwitchPermissionsTeamTask` | [app/Multitenancy/SwitchPermissionsTeamTask.php](../app/Multitenancy/SwitchPermissionsTeamTask.php) | Switch task: `makeCurrent()` sets the permission team id to the tenant id, and forgetting the tenant clears it. |
| Membership | `User::tenants()` / `Tenant::users()` | No separate table: a user belongs to a tenant when they hold any role there. `User::staffTenants()` is the subset where they hold `panel_user`. |
| Super admin | `users.is_super_admin` + `Gate::before` in `AppServiceProvider` | Installation-wide, passes every check, and sees all active tenants. It is not mass-assignable. |
| Admin panel | `->tenant(Tenant::class, slugAttribute: 'slug')` + [MakeFilamentTenantCurrent](../app/Http/Middleware/MakeFilamentTenantCurrent.php) (persistent tenant middleware) | Filament picks the tenant from the URL, and the middleware makes it spatie's current tenant, which also switches the permission team. |
| Web / Inertia | [ResolveCurrentTenant](../app/Http/Middleware/ResolveCurrentTenant.php) (web group) + `POST /tenants/{slug}/switch` | The tenant id is kept in the session. It is auto-selected when the user has exactly one tenant, and dropped if the user no longer belongs to it. Shared Inertia props: `tenant.current`, `tenant.available`. |

spatie-multitenancy's `tenant_finder` is **not used**: it runs before the session exists, so the middlewares above resolve the tenant instead. Queued jobs are tenant-aware by default (spatie config).

**Not done yet:** the tenant picker page and login on the Inertia side (guests are temporarily redirected to the admin login), a configurable tenant label (مدرسه / شعبه), and a Filament resource for managing tenants.

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

`.env` (from `.env.example`) sets `DB_CONNECTION=pgsql` with `DB_HOST=pgsql`, `APP_LOCALE=fa`, `APP_FAKER_LOCALE=fa_IR` and the compose ports. The app timezone stays UTC; Jalali/Tehran display is a presentation concern.

The ports are chosen so they don't clash with LMS (80, 5173, 3306, 8080), and both projects can run together. Defaults can be overridden in `.env`.

## Deployment
_(TODO: environments, how it's deployed.)_
