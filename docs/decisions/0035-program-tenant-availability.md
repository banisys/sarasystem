# 0035 — Educational programs offered in selected tenants or all tenants

- **Date:** 2026-10-30
- **Status:** accepted

## Context
Programs are managed in the primary tenant's admin panel (ADR 0018), but a program may be offered in one branch, several branches or every branch. The user wants this choice when creating a program. Its owner tenant and the tenants in which it is offered are different concepts.

## Options considered
- Copy the program into every target tenant — duplicates its form configuration, document requests and workflow, and breaks centralized preregistration.
- Store target tenant IDs in JSON — simple, but cannot enforce tenant foreign keys or use a normal Filament relationship selector.
- A many-to-many relation plus an explicit all-tenants flag — preserves one centrally managed program and distinguishes selected tenants from a dynamic selection of all tenants.

## Decision
- Programs remain tenant-owned through `BelongsToTenant` and managed in the primary tenant only. `student_programs.all_tenants` controls whether the program is offered in every active tenant. It defaults to true for existing and new programs, preserving previous behavior.
- Selected tenants are stored in `student_program_tenant` through `Program::availableInTenants()`. These are availability targets, not owners. The create/edit modal offers «همهٔ شعبه‌ها» (using the configured tenant label), or a required searchable multi-select of one or more active tenants. Empty or inactive selections are refused. The list displays the offering tenants.
- All-tenants mode includes tenants activated or created later. Previous explicit selections may remain stored while all-tenants mode is on; only the flag governs availability in that mode.
- `/preregister` supplies the permitted preferred-tenant options for each program and clears an incompatible choice when switching programs. The request enforces the same restriction. The existing all-tenants preference behavior remains: omit the primary tenant when other active tenants exist; a specifically selected primary tenant may be chosen.
- Final admission offers only the program's active target tenants. `AdmitStudent` checks the restriction before creating a user or learner. If a program was deleted, the existing admission behavior for historical applications is retained.

## Consequences
- No copies of programs, form settings, document requests or workflows are needed.
- Branches still cannot edit the central program catalog or see another tenant's records merely because a program is offered there.
- Deactivated tenants cannot be chosen publicly or for final admission, even if their assignment remains stored.
- Changing target tenants applies to open applications too; an outdated preferred tenant is no longer preselected for final admission. Existing admitted learner rows are unchanged.
