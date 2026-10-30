# 0018 — Learners, preregistration and admission

- **Date:** 2026-10-27
- **Status:** accepted (revised the same day: first written around a school's grades, see the end)

Extended by [ADR 0035](0035-program-tenant-availability.md): programs remain centrally managed but can be offered in one, several or all active tenants; preferred-tenant choices and admission follow that availability.

## Context
SaraSystem serves schools, universities, institutes and companies ([overview](../overview.md)). They all have **learners**: the people who are put into classes so that education can happen. A learner can be:
- a pupil (دانش‌آموز);
- a university student (دانشجو);
- an institute's trainee (هنرآموز / زبان‌آموز);
- an employee in a company's training (کارآموز).

Many organizations let people apply first and then admit them after a review. LMS did this for schools only (`School\Livewire\PreregisterForm` at `/school/preregister`):
- It created a `User` at once and kept the whole form as JSON on the user.
- The form was built around Iranian schools: stage and grade, previous school, last term's average, birth certificate, Tehran district and Kanoon.
- The review was a fixed «ارجاع / رد» screen. Forwarding created the `schl_students` row in a school and year.

We already have the same shape for hiring ([ADR 0014](0014-job-applications.md), [ADR 0016](0016-workflows.md)) and customizable forms ([ADR 0017](0017-customizable-forms.md)).

## Options considered
- **Like LMS (User at once + JSON, fixed review, school fields built in):**
  - Con: every rejected applicant becomes a user.
  - Con: only fits schools.
- **Like hiring, with generic built-in fields:**
  - A separate preregistration table, an admission workflow, and the user created on admission.
  - Only what every learner has is built in. What one kind of organization asks (a school's grade and average, a university's entrance rank, a company's department) is added by that organization as form fields.
  - Pro: one module for every kind of organization.
  - Con: a school sets up its questions once instead of getting them built in.

What the applicant chooses:
- **Grades (stage → grade):**
  - Con: a school concept.
- **Programs: a flat list the organization defines, like job positions:**
  - A program can be «پایه هفتم», «کارشناسی کامپیوتر», «زبان انگلیسی - مقدماتی» or «دوره‌ی بدو خدمت».
  - Pro: fits every organization.

## Decision
- **Module `Student`, the learner.** Its Persian name is set per installation:
  - `sarasystem.learner_label` / `learner_plural_label` (`LEARNER_LABEL`, default «فراگیر» / «فراگیران»), like the tenant label.
  - A school sets «دانش‌آموز», a university «دانشجو», and so on.
- **Learners** (`students`, `Student`) hold one person's membership as a learner in one tenant:
  - user, learner code, status (active / suspended / graduated / left) and dates;
  - one row per tenant and user;
  - no grade, average or program: putting learners into classes comes with the class/education features.
- **Programs** (`student_programs`, `Program`) are what people preregister for:
  - per tenant, active / inactive, sortable;
  - each with an optional **admission workflow** and its own **setup of the form** (ADR 0017).
  - Preregistrations keep a copy of the title (`program_title`).
- **Preregistrations** (`student_preregistrations`, `Preregistration`):
  - No user is created until admission.
  - **One open preregistration per national code** in the installation (partial unique index). Sending the form again while one is open updates it. This is only allowed from the mobile it was sent with, and only until a reviewer has it.
  - **Statuses:** submitted, in review, rejected, admitted. Rejected and admitted close it.
- **Primary tenant only.**
  - The form is at `/preregister` on the primary tenant's site; preregistrations belong to the primary tenant.
  - «پیش‌ثبت‌نام → متقاضیان ثبت‌نام / برنامه‌های آموزشی» are in the primary tenant's panel only (core trait `OnlyInPrimaryTenant`).
- **Built-in fields:**
  - Always asked: program, first and last name, nationality, national code (or a 12-digit کد اتباع), gender and mobile.
  - Customizable, required by default: birth date (3 to 90 years old).
  - Customizable, optional by default: the tenant the applicant would like, father name, phone, email, postal code, city and address.
  - Everything else is an added field. The local demo shows a school this way: the LMS student form's birth certificate, birth place, previous school and last average are added fields.
- **The mobile** is confirmed by SMS (a parent's for a child), with a honeypot and 10 per hour per IP, as in ADR 0014. Guest mobile confirmation is the core `App\Auth\GuestMobile`, shared with «همکاری با ما».
- **A national code that already has a user** is accepted: admission reuses that user. `users.national_code` was widened to 12 characters for foreign nationals.
- **Admission is a workflow** (`PreregistrationSubject`):
  - A new preregistration starts its program's workflow.
  - «ویرایش اطلاعات» can change the program, names and contact fields, and the answers to added fields.
  - The last approval asks for the tenant (default: the applicant's choice), the enrollment date, a learner code and roles. The `student` role is preselected when it exists and the approver may give it.
  - It then runs `AdmitStudent`: it creates or reuses the user, gives the roles and creates the learner row in that tenant.

## Consequences
- The same module serves a school, a university, an institute and a company; the difference is configuration (label, programs, form setup, workflow).
- School-specific data (grade, average, previous school) lives in added fields. It can't be filtered in lists yet (ADR 0017 known issue), and it is not structured for a later School module. A School module may add its own structured data on top of the learner.
- Rejected applicants leave no user behind. The case history is the review record.
- Programs are the primary tenant's, as job positions are. Branches can't define their own.
- Every preregistration costs at least one SMS.
- A site page can't use the path `/preregister` (route).

### Revision 2026-10-27
The first version of this ADR had a two-level grade tree (stage → grade) as the choice, and grade, birth certificate, birth place, previous school and last average as built-in fields. The learner row also had a grade. That made the module a school module. It was replaced the same day, before any release, by programs, generic built-in fields and a configurable learner label.
