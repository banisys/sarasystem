# 0014 — Job applications («همکاری با ما»)

- **Date:** 2026-10-25
- **Status:** accepted

## Context
Hiring an employee is a process with several steps. The first step is the public «همکاری با ما» form, where an applicant sends their details. Later steps (review, forwarding, approvals, tests, interviews) work on what was sent. LMS (`School\Livewire\EmployeePreregisterForm`) did this as follows:
- It created a `User` right away and stored the whole form in `users.info` (JSON).
- It marked the applicant with a disabled `schl_school_employees` row (`state = preregister`).
- It kept the résumé on the public disk.
- It offered school roles as the jobs to apply for.
- It had a section about Kanoon (a Saraye Danesh–specific organization).

## Options considered
- **Like LMS (User + JSON):**
  - Pro: the applicant has an account at once.
  - Con: every rejected applicant becomes a user.
  - Con: our `users` only has name/email/mobile/national code.
  - Con: the review state has no proper home.
- **Separate application table, user created on hiring:**
  - Pro: clean data, and the hiring workflow has its own place.
  - Con: hiring must copy the data into a user later.

## Decision
- **Module `Employee`.** Applications live in `employee_job_applications` (`JobApplication`). No user is created; that happens when someone is hired (later step).
  - Fixed fields are columns.
  - Education and work history are jsonb arrays, with dates stored as Gregorian `Y-m-d`.
- **Status.** `status` is an enum (`ApplicationStatus`), driven by the hiring workflow since ADR 0016. `closed_at` is set by the model when the status is a closing one (rejected, hired).
- **One open application per national code** in the whole installation:
  - A partial unique index (`national_code WHERE closed_at IS NULL`) enforces it.
  - Sending the form again while one is open **updates** that application, whatever tenant it is in, and replaces the résumé, but only from the mobile it was sent with. Otherwise the form says which number to use (masked, `0912***4567`).
  - After it is closed, a new application is created.
- **Primary tenant only.**
  - The form is at `/careers` on the primary tenant's site only; with no primary tenant it is 404, and `/{slug}/careers` is 404.
  - Applications belong to the primary tenant.
  - The admin resources «همکاری با ما → متقاضیان همکاری / موقعیت‌های شغلی» are reachable only in the primary tenant's panel (`OnlyInPrimaryTenant`).
- **Jobs** come from `employee_job_positions` (`JobPosition`, per tenant, active/inactive, sortable), not from roles, so the product works for companies and institutes too. The application keeps a copy of the title (`job_title`).
- **Form fields** follow LMS, with these changes:
  - The Kanoon section is dropped.
  - Work history, home phone and konkur rank are optional.
  - Military status is asked of Iranian men only.
  - University type/name are asked for university degrees only.
  - Foreign applicants give a 12-digit code (کد اتباع).
  - Dates are Jalali strings, typed or picked with `JalaliDateInput` (ADR 0015), validated by `App\Rules\JalaliDate`.
  - Since [ADR 0017](0017-customizable-forms.md), each organization can make most of these fields optional or leave them out, and add its own fields, for all positions or per position. The Kanoon section can come back that way as added fields.
- **Mobile confirmed by SMS before saving** (added 2026-10-25, `ApplicantMobile`):
  - The applicant gets a code («ارسال کد تأیید», `POST /careers/mobile/code`) and types it back (`POST /careers/mobile/verify`).
  - The confirmed number is kept in the session for 60 minutes, so the rest of the form can be filled without hurry. Saving uses it up.
  - The codes are the core `OneTimePassword` codes with a guest owner (applicants have no account): 2 min TTL, 5 tries, 60 s resend.
- **Spam:** no captcha. Instead:
  - a honeypot field (answered like a success, nothing saved);
  - 10 submissions per hour per IP;
  - 10 code sends/checks per hour per IP (`careers-mobile`), which also limits SMS cost.
- **Résumé:** a PDF of at most 10 MB, stored with medialibrary on the private `local` disk and downloaded only through the admin panel.

## Consequences
- The review steps are a workflow ([ADR 0016](0016-workflows.md)): statuses `in_review` / `hired` / `rejected` follow the case, the history is the case's. Re-sending the form is allowed only until a reviewer has the application.
- Unlike LMS, knowing someone's national code is not enough to overwrite their application: it takes the code sent to their mobile. An applicant who lost that number cannot update their open application; staff must close it first.
- Every application costs at least one SMS.
- If the primary tenant changes, existing applications stay with the old one and are no longer shown in any panel.
- A site page cannot use the path `/careers` (it is a route; `Page` rejects it already).
