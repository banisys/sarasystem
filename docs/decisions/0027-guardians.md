# 0027 — Parents and guardians of learners

- **Date:** 2026-10-29
- **Status:** accepted

## Context
The `parent` role exists (DemoSeeder, LMS), but nothing records whose parent someone is. The user wants parents to see their child's schedule, attendance and homework («کلاس‌های من», [ADR 0026](0026-my-classes.md)). Later they should get an SMS about absences.

The legacy LMS has a `parent` role and parents' data in the User module; this is not ported. The preregistration form ([ADR 0018](0018-student-preregistration.md)) only asks for the father's name, and the applicant's mobile (often a parent's) becomes the child's.

## Options considered
- **What is linked:**
  - A learner row (`students`, per tenant) to a guardian. This needs one link per branch, and only the Student module could use it.
  - **Two users: guardian and ward.** A child is the same child in every branch, and modules other than Student (Class, later SMS, contracts signed by a parent) can read it.
- **Where it lives:**
  - The Student module: then Class would depend on Student, which ADR 0022 avoids.
  - **The core (`app/`)**: a person relation that several modules use, like users themselves. Managed from the Student module's learner page.
- **Finding the guardian:** search all users / **by national code**. If a user with that code exists, they become the guardian and their account is not changed. Otherwise a user is made from the name, mobile and national code in the form. A branch clerk can't browse the whole installation's users.
- **Access for the guardian:** a guardian must hold a role in the ward's tenant to sign in there. Linking gives them the guardian role (`GUARDIAN_ROLE`, default `parent`, made with the label `GUARDIAN_ROLE_LABEL` if missing) under the role assignment rules ([ADR 0003](0003-role-assignment-rules.md)). Unlinking takes the role away when they have no other ward in that tenant.
- **SMS:** a `receives_sms` flag per link now, with sending left for later. The user asked to make absence SMS possible, not to build it yet.

## Decision
- **Core:**
  - Table `guardianships` (`App\Models\Guardianship`): guardian, ward, relationship ([GuardianRelationship](../../app/Guardians/GuardianRelationship.php): پدر / مادر / سرپرست قانونی / سایر), `receives_sms`, who made it. Unique per pair.
  - `User::wards()` / `User::guardians()`.
  - [Guardians](../../app/Guardians/Guardians.php): `link()`, `unlink()`, `findOrCreateGuardian()`, `wardsIn($guardian, $tenant)`, `role()`.
  - Rules: the ward is a member of the tenant, nobody is their own guardian, and the actor may give the guardian role.
- **Student module:** the learner page gets the tab «اولیا» ([GuardiansRelationManager](../../Modules/Student/app/Filament/Resources/Students/RelationManagers/GuardiansRelationManager.php)): add by national code (name and mobile for a new user), edit the relationship and SMS flag, remove. Who may edit the learner (`Update:Student`) manages it.
- **Class module:**
  - `/my-classes?for={ward}` and the session pages with `for` show a ward's calendar, sessions, content, homework, materials and the ward's own attendance, read only. A guardian never gets the attendance or content forms through a ward.
  - The header gets «کلاس‌های {نام}» for each ward in the tenant who is in a class.
- **Demo:** [GuardianDemoSeeder](../../Modules/Student/database/seeders/GuardianDemoSeeder.php) (in `module:seed Student`) gives the demo students learner rows and links each demo parent to the student with the same last name; principals get the learners permissions.

## Consequences
- Any module can now ask «who are this user's guardians?» (e.g. absence SMS through `SmsSender` to links with `receives_sms`, or a parent signing a minor's contract).
- A guardian linked in one branch sees the ward only in tenants where both hold roles. A ward in two branches needs the guardian role in both, which linking from each branch's learner page gives.
- The applicant's mobile in preregistration is still the child's; preregistration doesn't create guardians yet.
- Removing a guardian's role by hand (users screen) leaves the link; they just can't sign in to that tenant.
