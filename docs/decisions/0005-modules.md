# 0005 — Modular structure: platform core in `app/`, business areas in `Modules/`

- **Date:** 2026-10-23
- **Status:** accepted

## Context
`nwidart/laravel-modules` is part of the stack ([ADR 0001](0001-tech-stack.md)), but the first features (tenancy, users, roles, authentication, profile) were built in `app/` without an explicit decision. In legacy LMS everything is a module, including `Core` and `User`.

## Options considered
- **Everything in modules, the core included** (as in LMS): uniform, but every business module would then depend on `Core`/`User` modules. Module-to-module dependencies and load order become the norm, and the core gains nothing from being toggleable.
- **Platform core in `app/`, business areas in `Modules/`** (chosen).

## Decision
- **`app/` = the platform core** that every module builds on: tenancy (`Tenant`, `BelongsToTenant`), users, roles and role-assignment rules, authentication, profile, the admin panel provider and shared support code. The core never depends on a module.
- **`Modules/{Name}` = one business area each.** Examples: classrooms, exams, courses, finance. Every part ported from LMS lands in a module.
- Modules may depend on the core freely. A dependency between modules should be rare and explicit (listed in the module's `module.json` / docs).
- **Wiring** (done in the core, so modules need no boilerplate):
  - **Filament:** `AdminPanelProvider::discoverModules()` discovers `Modules/{Name}/app/Filament/{Resources,Pages,Widgets}` of every **enabled** module.
  - **Inertia:** module pages live in `Modules/{Name}/resources/js/Pages` and are rendered as `'{Name}::Page'`. `resources/js/app.js` resolves them, and `AppServiceProvider` registers the same namespaces for Inertia's test page finder. The `@` alias (Vite) points to `resources/js` for shared layouts and components.
  - **Assets:** there is no per-module Vite build. The app's Vite bundles module pages, and Tailwind scans `Modules/` automatically.
  - **Tests:** a `Modules` PHPUnit suite runs `Modules/*/tests`, and module tests extend `Tests\TestCase`.
  - **Migrations** are auto-discovered from enabled modules (`config/modules.php`).
- **Generator:** `php artisan module:make {Name} --inertia`, using our stubs in `stubs/nwidart-stubs` (`config/modules.php` → `stubs.enabled`). They differ from the vendor stubs:
  - The web routes use `['auth', 'auth.session', 'tenant']`.
  - There are no API routes.
  - The Inertia controller renders `Name::Page`.
  - The pages use `@/Layouts/AppLayout.vue`.
  - There are no Blade views or asset scaffolding.

## Consequences
- After creating a module, run `composer dump-autoload` (merge plugin). This needs no internet.
- A module can be disabled (`modules_statuses.json`). Its admin screens, routes and pages then disappear together.
- Enabling or disabling a module that has migrations needs care. Its tables stay, and other modules must not depend on them.
- The infrastructure was verified with a temporary `Sample` module (routes, Inertia page, Filament page, test suite, Vite build), which was then removed. The first real module brings the first module tests.
