# 0040 — Step-by-step tutorial on the live installation

- **Date:** 2026-10-31
- **Status:** accepted

## Context
When an installation is handed over, the person taking it over has to learn how the system works for their kind of organization: a language institute, a school chain or a company. The user wants a tutorial made of scenarios, one per kind of client. Each scenario is a roadmap of missions done in order («build a branch called …»). A mission ticks when it is done and the next one opens. The person switches to the role each mission needs. The tutorial runs **on the live installation**, and starting again at any point deletes the data the tutorial made.

## Options considered
- **Where it runs:** a separate training installation was proposed, but the user chose the live one. So the tutorial must keep its data apart and be able to delete it.
- **Telling a mission is done:** listening to events (fragile when the person takes another way, or deletes and makes again), or **checking the state** (chosen). Each mission asks whether the rows it needs exist among the rows made in the run.
- **Finding the tutorial's rows for deletion:** naming conventions or flags on every table (invasive), or **recording every row made in tutorial mode** (chosen).

## Decision
- Module `Tutorial`. Scenarios ([Scenario](../../Modules/Tutorial/app/Scenarios/Scenario.php)) have chapters of [Mission](../../Modules/Tutorial/app/Scenarios/Mission.php)s: steps, who does it, a link, the account to sign in with and a done check. They are registered in [Scenarios](../../Modules/Tutorial/app/Scenarios/Scenarios.php). The first scenario is [LanguageInstitute](../../Modules/Tutorial/app/Scenarios/LanguageInstitute.php), with the fewest options: a branch, an instructor and a registrar by quick entry, an admission workflow and program, a public preregistration, admission by the registrar, then a term, a class, members, a one-off session and the teacher's attendance (12 missions).
- **A run** (`tutorial_runs`, one per person) stores the missions done. Order is linear: only the current mission is checked ([TutorialProgress](../../Modules/Tutorial/app/Services/TutorialProgress.php)), and a mission done early ticks when its turn comes.
- **Tutorial mode** is a session key ([TutorialMode](../../Modules/Tutorial/app/Services/TutorialMode.php)). It counts only while the run's owner is signed in or impersonating, so it holds when they sign in as another account or fill a public form. While it is on, a listener on every model's `created` event writes the row into `tutorial_records` ([TutorialRecorder](../../Modules/Tutorial/app/Services/TutorialRecorder.php)).
- **Role switch:** a mission may name an account made earlier in the run. «ورود با حساب …» impersonates it through the existing impersonation ([ADR 0008](0008-impersonation.md)). Only super admins use the tutorial.
- **Starting again** ([TutorialReset](../../Modules/Tutorial/app/Services/TutorialReset.php)) deletes the recorded rows newest first, without model events, then the role assignments and notifications of deleted users and tenants, then the run. It is all or nothing: if a row from outside the tutorial still points at one of its rows, nothing is deleted.
- **The public site hides tutorial rows from everyone but their maker.** A core hook, [HiddenRecords](../../app/PublicSite/HiddenRecords.php), is applied to the site's tenants (footer, `/{slug}`) and to `/preregister`'s programs and tenants, so real visitors never see «شعبه ونک» or the practice program.
- **UI:** «آموزش گام‌به‌گام» in the admin panel (scenarios, roadmap, pause / resume, «شروع دوباره»). A bar at the top of every page shows the current mission, its link and the role switch: a Livewire component in the admin panel, and `TutorialBar.vue` with the shared `tutorial` prop on Inertia pages. Both poll.

## Consequences
- Only rows made with Eloquent in a web request are recorded. Rows made by queue workers or with query-builder inserts are not, and changes to rows that existed before are not undone. Missions should therefore only create, and module code should create rows with Eloquent.
- Files uploaded in the tutorial (media) stay on disk after a reset. The first scenario uploads none.
- Everything made while the mode is on counts as tutorial data, including real work done by mistake. The bar shows the mode on every page, and it can be paused.
- Admin lists (users, tenants, access map) do show tutorial rows; only the public site hides them.
- More scenarios (school, company) reuse the engine; each needs a test that walks its missions.
