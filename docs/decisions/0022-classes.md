# 0022 — Classes: terms, class roles and members (module `Class`)

- **Date:** 2026-10-29
- **Status:** accepted

## Context
The organization needs classes: groups of people who meet on a schedule, with sessions, attendance and what each session covered. The legacy LMS has them in its `School` module (`schl_classrooms`, `schl_classroom_plans`, `schl_weekly_schedules`, `schl_activity_sessions`, …). That design has several weaknesses:
- It is school-only: grade, academic year from Shahrivar to Mordad, school gender and report cards with fixed Persian months.
- Who is in a class is fixed in columns. Students are in `schl_students_classrooms`, `supporter_id` and `supervisor_id` are columns of the class, and teachers are reached only through a class plan. No other role can be added (e.g. a psychologist or an assistant coach).
- Only weekly schedules exist (every week, odd weeks or even weeks).
- Sessions are made only for online (Adobe Connect) schedules, by deleting and rebuilding every future session whenever a schedule is saved.
- There are two session concepts (`schl_sessions` and `schl_activity_sessions`).
- Attendance is a yes/no only.

SaraSystem serves schools, language institutes and companies, so classes must be generic. School-only data goes into a later `School` module. The user asked for the LMS model as inspiration only. LMS data is not migrated: the new module starts empty.

The work is split into steps:
1. Terms, holidays, class roles, classes and members (this ADR).
2. Schedules and the sessions built from them.
3. Attendance.
4. Teaching plans and session content.
5. The user panel.
6. The `School` module.

Later steps get their own ADRs.

## Options considered
- **Who is in a class:** fixed columns or tables per kind of person (LMS) / the system roles (`teacher`, `supporter`, …) / **a separate list of class roles**. With fixed columns, every new kind of person needs a schema change. The system roles are per tenant, not per class: an employee who supervises one class and teaches another can't be expressed. A separate list costs one more screen, but it says exactly what a person is in a class, and its flags drive attendance and content later.
- **Terms:** an academic year built in (LMS) / **a generic term** / none. A school's academic year, an institute's term and a company's training round are all terms. Classes without a term are allowed for one-off trainings.
- **Who defines terms and class roles:** each tenant / **the primary tenant for the whole organization**. This is the same as document and contract types (ADR 0019, ADR 0020): one list for every branch, so reports across branches agree.
- **Holidays:** organization-wide only / per tenant only / **both**. The primary tenant's holidays apply to every tenant (Nowruz). A tenant adds its own (a branch trip).
- **Online classes:** an Adobe Connect integration (LMS) / **only a meeting link for now**. The user chose the link. A provider integration can come later as a driver.
- **Sub-classes:** none / **one level, whose attendees must be attendees of the parent class**. For example, a lab group of a school class.

## Decision
- New module `Modules/Class` (PHP 8 accepts `Class` in a namespace; the class model is `Classroom`, because `Class` is not a valid class name).
- **Tables:**
  - `class_terms` (`Term`) and `class_roles` (`ClassRole`): defined in the primary tenant; read from anywhere with `definedTerms()` / `definedRoles()`.
  - `class_holidays` (`Holiday`): per tenant, plus the primary tenant's (`Holiday::applyingIn($tenant)`); a single day or a date range.
  - `classrooms` (`Classroom`): per tenant. It has a term (optional), a parent class (one level), title and code (unique per tenant), mode (in person / online / blended), location, meeting link (http/https only), capacity and dates.
  - `class_members` (`ClassMember`): user + class role + `joined_on` / `left_on`. Leaving sets `left_on`; the row stays for the class's history, and a person coming back reuses it.
- **Class role flags:**
  - `is_attendee`: attendance is taken for them, and they count toward the capacity.
  - `takes_attendance`
  - `records_content`
  - `allowed_role_ids`: the system roles a member must hold in the class's tenant (empty = any member of the tenant).
- **Rules** are in [ClassMembers](../../Modules/Class/app/Services/ClassMembers.php), not in the form:
  - A member holds a role in the class's tenant, and one the class role allows.
  - No duplicate role for one person in one class.
  - Attendees fit the capacity.
  - A sub-class's attendees are current attendees of its parent.
- **Admin group «کلاس‌ها»:**
  - «کلاس‌ها»: every tenant. Created and edited in a modal. The class page has the tab «اعضا» plus tabs other modules add through `ModelRelationManagers`, so `School` can add its own without Class depending on it.
  - «دوره‌های آموزشی» and «نقش‌های کلاس»: primary tenant only.
  - «تعطیلات»: every tenant. The organization's holidays are listed but can't be edited outside the primary tenant.
- **Permissions:** managing members needs `Update:Classroom`; there is no separate permission.

## Consequences
- Any organization can name its own people in a class (فراگیر، مدرس، ناظر، پشتیبان، کمک‌مربی…), and their flags decide who takes attendance and who records content in the next steps.
- Class roles hold role ids. A deleted system role leaves a dead id, which is ignored. A class role can't be deleted while someone holds it.
- Learners are users with a role in the tenant. The class doesn't link to the `students` row, so Class doesn't depend on Student. A learner who leaves the tenant (roles removed) stays a class member until `left_on` is set.
- Class subjects (several subjects with different teachers in one class) come with the schedules (step 2), as part of a schedule entry.
- The `School` module will add grade and subjects to classes through hooks (tabs, its own tables); Class stays generic.
