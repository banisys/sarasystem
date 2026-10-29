# 0028 — Parents in preregistration, and the learner's login mobile

- **Date:** 2026-10-29
- **Status:** accepted

## Context
Guardians exist since [ADR 0027](0027-guardians.md), but they are only added by hand after admission. The user wants a school to record three mobiles separately: the father's, the mother's and the student's own. A student without a mobile signs in with one of the parents' mobiles. Institutes and companies don't need any of this.

Until now the preregistration form had one mobile, confirmed by SMS ([ADR 0018](0018-student-preregistration.md)): usually a parent's for a child, and admission made it the child's account mobile. Users' mobiles are not unique ([ADR 0004](0004-authentication.md)), so siblings and a parent can share one. Someone whose mobile is shared signs in with their national code, and the SMS code goes to that mobile.

## Options considered
- **Only for schools:**
  - A check in the code for «the organization is a school»: there is no such setting, and the project's rule is that school concepts must be optional, not hard-coded.
  - **Built-in fields of the customizable preregistration form ([ADR 0017](0017-customizable-forms.md)), not asked by default.** A school asks them in «تنظیم فرم پیش‌ثبت‌نام» (the local demo does). Institutes and companies change nothing, and middle cases (an institute with children asking only the mother) are possible.
- **The child's login mobile:**
  - **Copy the chosen parent's mobile into the child's account and keep it in step with that parent's mobile.** Sign-in works unchanged.
  - Keep the child's mobile empty and send codes to a guardian's: this would change the core sign-in.
- **Parents' national code:** **required with the parent's details.** Accounts are found or made by national code; mobiles alone would create duplicates.
- **Which parents:** the father and the mother are separate fields, each required / optional / not asked. An optional parent is all or nothing. When both are optional, at least one must be given.

## Decision
- **Preregistration form** ([PreregistrationForm](../../Modules/Student/app/Forms/PreregistrationForm.php)):
  - New built-in fields, all not asked by default: `father`, `mother` (each name + national code + mobile), `learner_mobile` (the learner's own) and `login_mobile` (asked or not; when asked, required).
  - A new section «والدین» for the organization's added fields.
  - Columns on `student_preregistrations`: `father`, `mother` (jsonb), `learner_mobile`, `login_mobile_from` ([LoginMobileSource](../../Modules/Student/app/Enums/LoginMobileSource.php): learner / father / mother).
  - [StorePreregistrationRequest](../../Modules/Student/app/Http/Requests/StorePreregistrationRequest.php):
    - a parent's national code differs from the learner's, and the mother's from the father's;
    - the login mobile must be one that was typed;
    - Persian digits are accepted.
  - The SMS-confirmed mobile stays: it is the applicant's, for following the preregistration, and is used as the login mobile when the form doesn't ask for one.
- **Admission** ([AdmitStudent](../../Modules/Student/app/Services/AdmitStudent.php)):
  - A new learner's account gets `Preregistration::loginMobile()`.
  - Each parent is found by national code (an existing user is kept as is) or made, then linked as father / mother with SMS on, and gets the guardian role in the tenant.
  - The parent whose mobile the learner signs in with is marked on the link. A new learner then gets that parent's mobile on record. An existing learner is only marked if their mobile is already that parent's.
  - If the approver gave the learner no role in the tenant, admission with parents is refused.
- **Guardianships:**
  - `provides_login_mobile` (at most one per ward). `Guardians::useLoginMobileOf($ward, $guardianship | null, $ownMobile)` chooses it.
  - `Guardians::mobileChanged()` runs when a user's mobile changes (`User::updated` in `AppServiceProvider`). The ward's mobile follows the marked guardian's. A ward who changes their own mobile stops following.
- **Admin:**
  - The «اولیا» tab shows «موبایل ورود فرزند» and has the action «موبایل ورود …»: the learner's own (with a mobile) or one guardian's.
  - Adding a guardian can mark them at once.
  - The preregistration page shows the parents' section.

## Consequences
- A school's child without a phone signs in with their national code, and the code goes to the chosen parent's phone. Signing in with that mobile asks for a national code, since parent and child share it.
- Organizations that don't turn the fields on see no change.
- The parents' details can't be edited in the admission workflow's «ویرایش اطلاعات» yet. Mistakes are fixed after admission in the «اولیا» tab or on the users.
- Existing learners admitted before this keep their mobiles; the «موبایل ورود» action sets them up.
