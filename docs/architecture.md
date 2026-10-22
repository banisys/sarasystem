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
| Surface | Who | Built with |
|---|---|---|
| Admin panel | Head office, school management, staff | Filament |
| User panel | Students, parents, teachers, supporters | Inertia + Vue |
| Public site | Visitors (school info, registration, blog) | Inertia + Vue |

## Directory map
Currently the stock Laravel 13 skeleton (created 2026-10-23). It will be updated as modules are added.
```
app/            core application code (outside modules)
bootstrap/      app bootstrap, providers list
config/         configuration
database/       core migrations, factories, seeders
docs/           project docs for humans and agents (this folder)
public/         web root
resources/      views, css, js
routes/         web / console routes
tests/          Feature + Unit tests (PHPUnit)
```

## Modules
_(TODO: module list and boundaries — to be derived from the domain map in [legacy-lms.md](legacy-lms.md#mapping-to-sarasystem-domains).)_

## Tenancy
_(TODO: tenant = organization or school? single DB or DB-per-tenant? how Filament panels and Shield permissions are scoped.)_

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
