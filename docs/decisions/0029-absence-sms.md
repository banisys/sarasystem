# 0029 — SMS to guardians about absences

- **Date:** 2026-10-29
- **Status:** accepted

## Context
Attendance ([ADR 0024](0024-class-attendance.md)) and guardians with an SMS flag ([ADR 0027](0027-guardians.md), [ADR 0028](0028-guardian-details-and-login-mobile.md)) are in place. The user wants guardians told by SMS when the learner is marked «غایب». While developing, messages must go to a log, not a provider. The only SMS driver is `log` ([ADR 0004](0004-authentication.md)), which wrote to `laravel.log` among everything else.

## Options considered
- **When to send:** at once when attendance is saved / a daily digest. **At once:** a parent wants to know the same morning. A digest can come later if parents find it too much.
- **Which statuses:** **absent only**, as asked. «تأخیر» and «غیبت موجه» send nothing.
- **Duplicates:** send on every save / **once per absence**. `absence_notified_at` on the attendance row is set after sending; saving again, or marking present and then absent again, sends nothing more. A correction («present after all») is not sent.
- **How:** synchronously after the attendance transaction / **a queued job per absence** ([SendAbsenceNotice](../../Modules/Class/app/Jobs/SendAbsenceNotice.php), dispatched after commit). The teacher's save doesn't wait for a slow provider. A failed send throws, and the job is tried again (3 tries, after 1 and 5 minutes); the absence is marked told only after its messages went out. The job reads the attendance again, so an absence corrected before it runs sends nothing. (First built synchronously the same day, then moved to the queue at the user's request.)
- **Who:** guardians with «پیامک» on and a mobile; one message per distinct mobile (two links with the same mobile get one). Learners without such a guardian (e.g. adults, companies' trainees) get nothing. Sending to the learner themselves was not asked.
- **Turning it off:** per installation with `CLASS_ABSENCE_SMS` (default on). Without guardians nothing is sent anyway, so non-school organizations need no setting.
- **Development log:** keep `laravel.log` / **a channel of its own**. The `log` driver writes to the `sms` log channel (`storage/logs/sms.log`, `SMS_LOG_CHANNEL`), so login codes and notices are easy to find.

## Decision
- [AbsenceNotices](../../Modules/Class/app/Services/AbsenceNotices.php) (Class module):
  - `queue()`: [AttendanceRegister::record()](../../Modules/Class/app/Services/AttendanceRegister.php) calls it with the rows it saved, so it covers both the admin panel and «کلاس‌های من».
  - `send()`: run by the job; builds the message, e.g. «امیر محمدی در جلسه‌ی «هفتم الف» (ریاضی) شنبه 1405/08/09 ساعت 08:00 غایب بود. شعبه غرب - سرا سیستم».
- The job is tenant-aware (spatie's default) and runs on the `database` queue. Locally the `queue` service of `docker-compose.yml` (`queue:work --tries=3`) runs it; tests use the `sync` queue.
- `class_attendances.absence_notified_at`; config `class.attendance.absence_sms`.
- `config/sms.php` gets `log_channel` (default `sms`). `config/logging.php` gets the channel `sms` (`storage/logs/sms.log`). `LogSmsSender` writes there.

## Consequences
- Guardians hear about each absence once, as soon as the teacher saves it.
- A wrong «غایب» corrected later is not followed by a correction SMS.
- A queue worker must run: the `queue` service locally, `queue:work` under a process manager in production (restarted on deploy). Without one, absences are not sent until it runs.
- A retry after a partial failure may send a guardian the same message twice.
- Login codes in development are now in `storage/logs/sms.log`, not `laravel.log`.
