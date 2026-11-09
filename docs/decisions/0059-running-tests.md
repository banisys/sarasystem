# 0059 — Running the tests: what to run, and what makes them faster

- **Date:** 2026-11-05
- **Status:** accepted

## Context
With 581 tests, the whole suite took about 50 s, and it was being run for almost every task. `php artisan test --parallel` (16 processes on the dev machine) also failed with PostgreSQL «out of shared memory», so `--processes=4` was used.

We measured on the dev machine (i7-13620H laptop: 6 performance and 4 efficiency cores, 16 threads). Runs vary by about 10 s, partly because the machine heats up, and most settings were run only once or twice.

| Setting (whole suite, 4 processes unless noted) | Time |
|---|---|
| As it was | 48–59 s |
| Xdebug off | 64 s |
| Xdebug off, OPcache on in the CLI | 55–59 s |
| Database on tmpfs without fsync | 58 s |
| All of these, 8 / 12 / 16 processes | 48 / 45 / 47 s |
| As it was, 1 process | 157 s |

- PHP does most of the work; `PDOStatement::execute` was about 4% of a profiled typical test file.
- Tests get slower as processes are added, so going past 8 processes gains little.
- Migrations are not the bottleneck: the whole overhead outside the tests is about 4 s with 1 process.
- `Modules/Tutorial/tests` took 53 s, about a third of the suite's test time, and `DefaultRoles::install()` was the main reason: 1.3 s and 533 queries each time, used by its 28 tests. `Permission::findOrCreate()` forgets the permission cache with each permission it makes and reads it all back for the next.

## Options considered
- **Run only the tests a change touches during a task** — the largest gain (a module's tests take seconds instead of the whole suite); module-only changes keep this scope through commit; shared changes require a final whole-suite run.
- **Make `DefaultRoles::install()` cheap** — fixes the real hot spot, and installations benefit too.
- **Share one install between tests (static copy of the rows)** — also fast, but adds test-only machinery and leaves the slow install in place.
- **Database on tmpfs, OPcache in the CLI, Xdebug off, `schema:dump`** — no gain beyond the noise in the measurements (`schema:dump` was not run; migrations cost too little for it to matter), and more setup to keep.
- **Trim the data in the slowest tests** — possible later; not measured.

## Decision
- While working, run only the tests of what the change touches. Run the whole suite once at the end of a task only if it changes something shared ([conventions.md → Testing](../conventions.md#testing)).
- Run the whole suite with `--parallel --processes=8`.
- `DefaultRoles::install()` makes the missing permissions in one `insertOrIgnore` statement and reads them back in one query.
- The `pgsql` service runs with `max_locks_per_transaction=256`, so a run with more processes no longer fails.

Result, measured the same way on 583 tests: `Modules/Tutorial/tests` 19 s, whole suite 27–36 s with 8 processes, 37 s with 16, 43 s with 4, 112 s with 1.

## Consequences
- **Amended 2026-11-09:** a commit alone does not require the whole suite. Module-only changes run affected tests; changes to shared code still require one final whole-suite run.
- A bug outside the affected tests may show up only in a later whole-suite run. This is the accepted tradeoff for faster module-only work.
- `DefaultRoles::install()` makes permissions without model events, so nothing that listens for a new `Permission` hears them. Nothing does today, and the tutorial must not record them anyway.
- `max_locks_per_transaction` also applies to the local application database. Production settings are separate and not affected by this file.
- These numbers belong to one laptop. Measure again before relying on them on another machine.
