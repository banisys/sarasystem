# 0042 — Module manager page

- **Date:** 2026-11-01
- **Status:** accepted

## Context
Clients can pay per feature ([ADR 0041](0041-independent-modules-per-feature-licensing.md)), so a super admin needs to switch modules on and off without the command line. LMS has such a page (`/cpadmin/core/config/modules`, `ModuleManager` and `ModuleStatusService`): a list of modules with enable / disable, writing `modules_statuses.json` and clearing caches. It protects only `Core` and `User`, ignores dependencies between modules, and does not prepare a module that is switched on.

## Options considered
- **Port as is:** simple, but a module with unmet needs could be switched on or off and break the system, and a first-time module would have no tables or permissions.
- **Page in a module:** it would disappear when its own module is off.
- **Page and service in the core, with declared dependencies and preparation (chosen).**

## Decision
- [ManageModules](../../app/Filament/Pages/ManageModules.php) («ماژول‌ها», `/cpadmin/{tenant}/modules`) and the service [ModuleManager](../../app/Features/ModuleManager.php) live in the core. Super admin only; `IndependentOfTenant`.
- The status stays in `modules_statuses.json` (nwidart).
- `requires` in `module.json` lists the modules a module builds on (School: Class, Student). A module that an enabled module requires can't be switched off; one whose requirements are off can't be switched on. Both are refused with a message naming the modules. Requirements are not switched on automatically.
- No module is protected: the core is not a module.
- Switching on prepares the module in fresh processes (the running one booted without it): `module:migrate {Name} --force`, `shield:generate --all --panel=admin --ignore-existing-policies` and `roles:install-defaults --add-missing-permissions`. If a step fails, the module is switched off again and the error is reported. Switching off keeps all data and permissions.
- Both directions then run `optimize:clear` and `permission:cache-reset`, and the page reloads fully so the menu matches.

## Consequences
- Selling features one by one needs no shell access.
- `modules_statuses.json` is tracked in git and is written at runtime, so it must be writable by the web user, and a deploy that overwrites it undoes the switches. Keep the installation's own file out of deploys if that matters.
- Switching modules is not audited yet, and there is no per-module license or expiry.
