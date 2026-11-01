# 0041 — Independent modules for per-feature licensing

- **Date:** 2026-11-01
- **Status:** accepted

## Context
Some clients pay per feature. An installation must keep working when a module the client hasn't bought is disabled (for example `Contract`), with no errors, broken screens or missing tables. [ADR 0005](0005-modules.md) already says that dependencies between modules should be rare and explicit. This ADR makes it a product requirement.

An audit on 2026-11-01 disabled each module in turn and ran the rest of the suite:
- **Contract, Document, Exam, Site, School, Tutorial off:** the application code works. The only failures are tests that assume another module is there.
- **Employee or Student off:** Contract and Document work through the core hooks (`ContractParty`, document holders, `ModelRelationManagers`). Some of their tests use employees, learners or programs.
- **Student off:** School fails (`students` table, `Modules\Student\Models\Student`).
- **Class off:** School's migrations fail (foreign keys to `class_terms` / `class_subjects`), and so does the whole application's migration.
- The Tutorial's `LanguageInstitute` scenario uses Class, Employee and Student classes directly.

## Options considered
- **Every module fully standalone:** clean, but some areas only make sense on top of another one (school report cards need classes). Rebuilding them on the core alone would duplicate whole modules.
- **Free dependencies between modules:** simpler to write, but one disabled module can break others, so features can't be sold one by one.
- **Independent by default, with a few explicit dependencies (chosen).**

## Decision
- A module depends only on the core in `app/`. When it needs to work with another module, it uses a core hook (contracts / interfaces, registries, events, `ModelRelationManagers`, `ClassroomExtensions`-style hooks owned by the module that is being extended) and does nothing when the other module is disabled.
- When one module really builds on another (a dependent module: School on Class and Student), the dependency is declared in the dependent module's `module.json` (`requires`) and in `architecture.md`. Its migrations and code may then use the other module directly. The pair is sold together, and enabling the dependent module requires the other one.
- Features that touch several modules (tutorial scenarios, default-role grants, workflow steps, tabs on people's pages) check that the other module is enabled and are hidden otherwise.
- Tests: a module's tests that need another module skip when it is disabled. Core tests don't assume any module.
- Every new module or cross-module feature is checked with the module disabled before it is done (see [conventions.md → Independent modules](../conventions.md#independent-modules-adr-0041)).

## Consequences
- A client's installation can turn off any module except the base of a declared dependency without breaking the rest.
- More hooks in the core, and slightly more code for cross-module features.
- To fix from the audit: declare School `requires` Class and Student; let a tutorial scenario list the modules it needs and hide it without them; make the tests named above skip when their other module is off; make sure enabling a module fails clearly when what it requires is off.
- Turning modules on and off is done by whoever deploys the installation (`modules_statuses.json` / `php artisan module:disable`). There is no license screen yet.
