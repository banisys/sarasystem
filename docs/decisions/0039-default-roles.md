# 0039 — Default roles shared by every kind of organization

- **Date:** 2026-10-31
- **Status:** accepted

## Context
After the demo data was removed, an installation started with only `super_admin` and `panel_user`. The guardian role was made only when the first guardian was linked, and admission preselected a `student` role that no longer existed. Every organization had to build its roles from nothing. SaraSystem serves school chains, language institutes and companies ([ADR 0002](0002-tenancy-model.md)). They use different words, but they need the same jobs.

## Options considered
- **No default roles**: fully neutral, but every installation starts empty, and code that relies on a role (admission, guardians, class roles) has nothing to point at.
- **Default roles plus a «school pack»** (deputies, counsellor, …) chosen at install: more ready-made for schools, but it keeps school-only roles in the product. The user decided against it: each organization adds such roles itself.
- **A small set of generic default roles** (chosen).

## Decision
- [DefaultRoles](../../app/Authorization/DefaultRoles.php) defines nine global roles with fixed keys and changeable Persian labels: `org_admin` «مدیر ستاد», `tenant_manager` «مدیر {tenant_label}», `education_officer` «کارشناس آموزش», `registrar` «کارشناس ثبت‌نام», `finance_officer` «کارشناس مالی», `instructor` «مدرس», `learner` (`LEARNER_LABEL`), the guardian role (`GUARDIAN_ROLE` / `GUARDIAN_ROLE_LABEL`) and `staff` «کارمند».
- **Permissions come from modules.** Each module calls `DefaultRoles::grant()` in its service provider for its own screens. A permission given to an officer also goes to `tenant_manager`, and from there to `org_admin`. This keeps the ADR 0003 subset rule: a manager can always assign the roles below them. `instructor`, `learner`, the guardian role and `staff` get no admin permissions; they use the user panel.
- **Install once, then hands off.** `php artisan roles:install-defaults` (run by `DatabaseSeeder`) makes only the missing roles, with their default permissions. Existing roles are not touched, so the organization's own changes stay. `--add-missing-permissions` adds missing default permissions (e.g. after a new module) without removing any.
- Default roles are **system roles**: they can't be deleted and their key can't change. Their label and permissions can.
- Modules add other defaults with `DefaultRoles::installing()`. The Class module makes the class roles فراگیر (attendee, `learner` only), مدرس (attendance and content, `instructor` only), ناظر (attendance) and پشتیبان. It does this only while the installation has no class role at all.
- Admission preselects `learner` (it was `student`).
- `panel_user` stays separate: it only lets a person into the admin panel. Holding a default staff role doesn't grant panel access on its own.

## Consequences
- A new installation is usable after `db:seed`. School-only roles (deputies, counsellor, psychologist, …) are made by the organization.
- New modules must call `DefaultRoles::grant()` for their screens (conventions → Creating a module). After a module is added to a running installation, run `roles:install-defaults --add-missing-permissions`.
- The code may rely on the default role keys (`DefaultRoles::LEARNER`, …). Tenant-specific roles with the same name are ordinary roles.
- A module's later-added defaults (`installing()`) must stay safe to run again, and must not override what the organization changed.
