# SaraSystem

> Education management platform combining LMS (learning), TMS (training management) and SIS (student information). It is a redesign of the legacy **LMS** project at `/home/ali/Projects/LMS` (`../LMS`) — use it as read-only reference; never modify it unless asked.

This file is loaded into every agent session. Keep it short; details live in `docs/`.

## Project docs — read before working

| File | Read it when |
|---|---|
| @docs/overview.md | Always — goals, scope, users |
| @docs/progress.md | Always — current state, what's next, known issues |
| [docs/architecture.md](docs/architecture.md) | Touching structure, modules, data flow, infra |
| [docs/conventions.md](docs/conventions.md) | Writing code — style, naming, commands, testing |
| [docs/glossary.md](docs/glossary.md) | Meeting a domain term you don't know |
| [docs/legacy-lms.md](docs/legacy-lms.md) | Porting, comparing or looking up how something worked in `../LMS` |
| [docs/decisions/](docs/decisions/) | Before changing something that looks deliberate |

## Commands

```bash
docker compose up -d          # app → http://localhost:8000, Vite 5174, PostgreSQL 5432
docker compose exec laravel.test bash   # run php/composer/npm inside the container
# TODO: test / lint / build — once the Laravel app exists
```
Containers have no internet: when a package must be installed, give the user the command to run.

## Rules for agents

1. **Docs are part of the change.** When a task changes something a doc describes, update that doc in the same task. A task is not done until the docs match the code.
2. **Update `docs/progress.md`** at the end of every meaningful task: move items between Done / In progress / Next, add known issues you found.
3. **Record decisions.** When you (or the user) pick between real alternatives — library, data model, API shape, structure — add an ADR in `docs/decisions/` using `0000-template.md`, and link it from `architecture.md` if relevant.
4. **New domain term?** Add it to `docs/glossary.md`.
5. **Don't duplicate.** Docs explain *why* and *how things fit together*; don't copy what's obvious from the code. Prefer linking to files (`src/foo.ts`) over pasting code.
6. **Keep this file lean.** Only add here what every session needs; put the rest in `docs/`.
7. If the docs and the code disagree, trust the code, fix the doc, and mention it to the user.
8. **Never copy code from `../LMS` directly.** Port only the parts the user names, one part at a time. Review the LMS code first, then rewrite it for this stack and refactor where needed. See [conventions.md → Porting from LMS](docs/conventions.md#porting-from-lms).
9. **Commits:** when a piece of work reaches a sensible commit point, tell the user and propose a commit message (Conventional Commits, English). Commit only after the user approves.
