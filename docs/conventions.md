# Conventions

## Working environment
_(Carried over from how the user works on LMS; confirm once the SaraSystem stack is decided.)_
- Development runs in Docker (Laravel Sail for LMS); run project commands inside the container.
- Containers have **no internet access**. If a package needs installing, give the user the exact command and let them run it.
- The user pulls Docker images through the ArvanCloud mirror and then tags them with the standard name, so `docker-compose.yml` keeps standard image names. Example: `docker pull docker.arvancloud.ir/postgres:18-alpine && docker tag docker.arvancloud.ir/postgres:18-alpine postgres:18-alpine`.
- Talk to the user in Persian (Persian script); code, identifiers and these docs stay in English.
- Laravel Boost is part of the stack: once installed, use its MCP tools (docs search, schema, tinker, logs) and its AI guidelines before guessing about Laravel/Filament/Inertia APIs. Keep Boost-generated guidelines and these docs from contradicting each other.

## Porting from LMS
Nothing is copied from `../LMS` as-is. Port only on the user's request, one named part at a time:

1. **Review** the LMS implementation of that part: models, tables (live DB, read-only), Filament resources, controllers/ViewModels, bridges and business rules. Note bugs, dead code, MySQL-specific SQL, legacy patterns and overlaps (see [legacy-lms.md](legacy-lms.md)).
2. **Summarize** for the user before writing code: what the part does, which business rules must be kept, what you plan to drop or change and why. Ask when a rule is unclear instead of guessing.
3. **Rewrite** it for this stack ([ADR 0001](decisions/0001-tech-stack.md)): clean PostgreSQL schema and naming, Filament for admin, Inertia/Vue for user-facing parts, Spatie packages instead of the legacy subsystems, and no ViewModels or legacy theme/block layer. Refactor wherever the legacy design is weak.
4. **Document** it: update the module's docs and `progress.md`. Record any real design choice as an ADR. In `legacy-lms.md`, note that the part has been ported and where it now lives.

## Code style
_(TODO: formatter/linter, naming rules, file naming.)_

## Patterns we use
_(TODO: e.g. how errors are handled, how data fetching is done, where business logic lives.)_

## Patterns we avoid
_(TODO: things that look tempting but we decided against — link ADR if any.)_

## Testing
_(TODO: framework, where tests live, what must be tested, how to run.)_

## Git
- Default branch: `main`. _(TODO: branch naming for features.)_
- Commit messages: [Conventional Commits](https://www.conventionalcommits.org/) in English, e.g. `feat(school): add classroom resource`, `docs: record tenancy ADR`, `chore: add docker compose`.
- Agents never commit on their own. At a sensible commit point, propose the message and commit only after the user approves.
