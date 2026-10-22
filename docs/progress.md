# Progress

_Agents: update this at the end of every meaningful task. Newest entries first in the log._

## Current focus
Defining scope of the redesign: which LMS features to keep, drop and add.

## In progress
- 

## Next
- Install the stack packages from ADR 0001 (check Filament 5 compatibility of each plugin first; user runs the installs).
- Install Laravel Boost and merge its guidelines into `CLAUDE.md` without losing our rules. The skeleton's bootstrap `CLAUDE.md`/`AGENTS.md` stub was intentionally not copied.
- Configure Vite for Docker (host `0.0.0.0`, port 5174, HMR host `localhost`) when setting up Inertia.

## Done
- 2026-10-23 — Laravel 13.38 skeleton added (PHP 8.5.9), `.env` on PostgreSQL, migrations run, default tests pass, http://localhost:8000 → 200.
- 2026-10-22 — PostgreSQL 18.6 container running (`postgres:18-alpine`, pulled via ArvanCloud mirror).
- 2026-10-22 — `git init` (branch `main`), `.gitignore`, `docker-compose.yml` (app + PostgreSQL 18).
- 2026-10-22 — Tech stack decided → [ADR 0001](decisions/0001-tech-stack.md).
- 2026-10-22 — Deep survey of `../LMS` (code + live DB, read-only): architecture concepts, module usage, domain facts, dead tables → `docs/legacy-lms.md`, `docs/glossary.md`, `docs/overview.md`.
- 2026-10-22 — Surveyed `../LMS`; module inventory in `docs/legacy-lms.md`.
- 2026-10-22 — Project docs scaffold created.

## Known issues / tech debt
- 

## Open questions
_(Things that need a decision from the user.)_
- Tenancy model: tenant = organization (SaaS) or school/branch? Single DB or DB-per-tenant?
- Which LMS modules/features to drop, which new ones to add?
- What exactly does TMS cover (vs. existing Course/Academy/Employee)?
- LMS overlaps to resolve: `Feedback` tickets vs `Ticket` module; `Course` module vs `School\Course`; `schl_exam_*` vs `exam_*`.
- Data migration: migrate live LMS data (MySQL → PostgreSQL ETL) or start with a fresh DB?
- Drop the dead cafeerent tables/roles (cars, tours, booking, cip, entity, kanoon, …)? See `legacy-lms.md` → Dead weight.
