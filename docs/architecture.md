# Architecture

## Tech stack
Full list of packages and the reasoning: [ADR 0001](decisions/0001-tech-stack.md).

| Layer | Choice |
|---|---|
| Backend | Laravel 13, modular (`nwidart/laravel-modules`) |
| Admin panel (staff) | Filament 5 + plugins (Shield, Impersonate, Settings, Tags, Media Library, Excel, Apex Charts, Jalali) |
| Public site & user panel | Inertia.js + Vue 3 + Tailwind CSS |
| Database | PostgreSQL |
| Multi-tenancy | `spatie/laravel-multitenancy` *(tenant model TBD)* |
| AI dev tooling | Laravel Boost |

## Applications / surfaces
| Surface | Who | Built with | URL |
|---|---|---|---|
| Admin panel | Head office, school management, staff | Filament | `/cpadmin` (same prefix as LMS) |
| User panel | Students, parents, teachers, supporters | Inertia + Vue | _(TBD)_ |
| Public site | Visitors (school info, registration, blog) | Inertia + Vue | `/` |

### Admin panel (Filament)
- Provider: [app/Providers/Filament/AdminPanelProvider.php](../app/Providers/Filament/AdminPanelProvider.php). The panel id is `admin`, the path is `cpadmin`, the brand comes from `APP_NAME` and the primary color is Violet.
- Registered plugins: Shield (roles & permissions UI) and ApexCharts. The other Filament packages are used per component and need no panel registration:
  - Jalali: `->jalali()` on DatePicker/DateTimePicker, `->jalaliDate()` on TextColumn/TextEntry
  - Spatie media/tags/settings form fields
  - Excel export actions
  - Impersonate action
- **Access:** `User::canAccessPanel()` lets in only users with Shield's `super_admin` or `panel_user` role. Shield's `HasPanelShield` trait is deliberately **not** used, because it gives `panel_user` to every new user, and students and parents must not reach the admin panel.
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
_(TODO: tenant = organization or school? single DB or DB-per-tenant? how Filament panels and Shield permissions are scoped.)_

Current state: `spatie/laravel-multitenancy` is installed and `config/multitenancy.php` is published, but tenancy is **not configured**. No landlord migration, tenant model or tenant finder exists yet. Spatie permission `teams` is off. Both wait for the tenancy ADR; changing `teams` later means editing the permission migration and running `migrate:fresh` (fine while there is no real data).

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
