# 0018 — Student preregistration and admission

- **Date:** 2026-10-27
- **Status:** accepted

## Context
Families preregister a student on the public site, and staff then review the request and admit the student into a school. In LMS this was done as follows:
- The form was `School\Livewire\PreregisterForm` at `/school/preregister`.
- It created a `User` at once (status `STUDENT_PREREGISTER`) and kept the whole form as JSON in `users.school_preregister_info`.
- It refused a national code that already had a user.
- The review was a fixed «ارجاع / رد» screen (`PreregisterWorkflow`). Forwarding created the `schl_students` row in a school and year, and gave the `student` role.
- The form asked for:
  - the student: name, father name, nationality, national code, birth certificate number, birth date and place, gender;
  - contact: mobile, home phone, email, postal code, city, Tehran district, address;
  - school: stage and grade, school, previous school, last term's average;
  - Kanoon membership and counter;
  - a captcha.

We already have the same shape for hiring ([ADR 0014](0014-job-applications.md), [ADR 0016](0016-workflows.md)). We also have no School module yet: there are no schools other than tenants, no academic years and no grade tree.

## Options considered
- **Like LMS (User at once + JSON, fixed review screen):**
  - Con: every rejected family becomes a user.
  - Con: a fixed review is not configurable per organization.
  - Con: the review history has no proper home.
- **Like hiring: a separate preregistration table, an admission workflow, the user created on admission:**
  - Pro: same rules and screens as hiring.
  - Pro: the organization designs the review steps.
  - Con: data is copied into a user later.

For the grades:
- **Wait for a School module:**
  - Con: blocks the feature.
- **A small two-level list in this module (stage → grade):**
  - Pro: it plays the role job positions play for hiring (workflow and form setup per stage).
  - Con: a later School module must take it over or link to it.

## Decision
- **Module `Student`**, built like `Employee`. Preregistrations live in `student_preregistrations` (`Preregistration`). No user is created until admission.
  - **One open preregistration per national code** in the installation (partial unique index). Sending the form again while one is open updates it. This is only allowed from the mobile it was sent with, and only until a reviewer has it.
  - **Statuses:** submitted, in review, rejected, admitted. Rejected and admitted close the preregistration.
- **Primary tenant only.**
  - The form is at `/preregister` on the primary tenant's site; preregistrations belong to the primary tenant.
  - «پیش‌ثبت‌نام → متقاضیان ثبت‌نام / مقاطع و پایه‌ها» are in the primary tenant's panel only. The trait `OnlyInPrimaryTenant` moved to the core for this.
- **Grades** (`student_grades`, `Grade`) have two levels:
  - A stage (مقطع, no parent) with its grades (پایه).
  - A stage without grades is chosen itself, so a language institute can list flat levels.
  - The stage holds the **admission workflow** and its own **setup of the form** ([ADR 0017](0017-customizable-forms.md)).
  - The preregistration and the student keep a copy of the title (`grade_title`, «متوسطه اول - هفتم»).
- **School choice:** «{tenant label} مورد نظر» offers the active tenants other than the primary one (usually a head office), or the primary tenant alone in a single-school installation. It is optional by default. The approver picks the actual tenant on admission.
- **Form fields** follow LMS, with these changes:
  - The Tehran district and the Kanoon fields are dropped. Both are Saraye Danesh–specific and can come back as added fields.
  - The city is typed, since we have no locations table.
  - The captcha is replaced by an **SMS check of the mobile** (usually a parent's), a honeypot and a limit of 10 per hour per IP, as in ADR 0014. Guest mobile confirmation is now the core `App\Auth\GuestMobile`, shared with «همکاری با ما».
  - A national code that already has a user is accepted: admission reuses that user.
  - Foreign students give a 12-digit code (کد اتباع). `users.national_code` was widened to 12 characters for them and for hired foreign applicants.
  - Defaults follow LMS: birth place, email, postal code, last average and the school choice are optional. The organization can change this and add fields.
- **Admission is a workflow** (`PreregistrationSubject`):
  - A new preregistration starts its stage's workflow.
  - «ویرایش اطلاعات» can change the grade, names and contact fields, and the answers to added fields.
  - The last approval asks for the tenant (default: the family's choice), the grade, the enrollment date, a student code and roles. The `student` role is preselected when the approver may give it.
  - It then runs `AdmitStudent`: it creates or reuses the user, gives the roles and creates a `Student` row in that tenant. There is one student row per tenant and user.
- **Students** (`students`, `Student`) hold the enrollment only: grade, student code, status (active / suspended / graduated / left) and dates. They are listed in «دانش‌آموزان» in every tenant and edited in a modal.

## Consequences
- Rejected families leave no user behind. The case history is the review record.
- There are no academic years or classes yet. The student row is the tenant-level enrollment, and a later School module adds years and classes on top of it, and may take over `student_grades`.
- Grades are the primary tenant's, as job positions are. Branches can't define their own grades.
- Every preregistration costs at least one SMS.
- A site page can't use the path `/preregister` (route).
