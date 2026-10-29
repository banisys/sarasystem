# 0029 — SMS to guardians about absences

- **Date:** 2026-10-29
- **Status:** accepted

## Context
Attendance ([ADR 0024](0024-class-attendance.md)) and guardians with an SMS flag ([ADR 0027](0027-guardians.md), [ADR 0028](0028-guardian-details-and-login-mobile.md)) are in place. The user wants guardians told by SMS when the learner is marked «غایب». While developing, messages must go to a log, not a provider. The only SMS driver is `log` ([ADR 0004](0004-authentication.md)), which wrote to `laravel.log` among everything else.

## Options considered
- **When to send:** at once when attendance is saved / a daily digest. **At once:** a parent wants to know the same morning. A digest can come later if parents find it too much.
- **Which statuses:** **absent only**, as asked. «تأخیر» and «غیبت موجه» send nothing.
- **Duplicates:** send on every save / **once per absence**. `absence_notified_at` on the attendance row is set after sending; saving again, or marking present and then absent again, sends nothing more. A correction («present after all») is not sent.
- **How:** queued job / **synchronously after the attendance transaction**, each message in a `try` that reports failures. A failed SMS never undoes the attendance. With one message per absent learner per guardian this is fast enough; a queue can come with a real provider if it is slow.
- **Who:** guardians with «پیامک» on and a mobile; one message per distinct mobile (two links with the same mobile get one). Learners without such a guardian (e.g. adults, companies' trainees) get nothing. Sending to the learner themselves was not asked.
- **Turning it off:** per installation with `CLASS_ABSENCE_SMS` (default on). Without guardians nothing is sent anyway, so non-school organizations need no setting.
- **Development log:** keep `laravel.log` / **a channel of its own**. The `log` driver writes to the `sms` log channel (`storage/logs/sms.log`, `SMS_LOG_CHANNEL`), so login codes and notices are easy to find.

## Decision
- [AbsenceNotices](../../Modules/Class/app/Services/AbsenceNotices.php) (Class module) sends the message, e.g. «امیر محمدی در جلسه‌ی «هفتم الف» (ریاضی) شنبه 1405/08/09 ساعت 08:00 غایب بود. شعبه غرب - سرا سیستم». [AttendanceRegister::record()](../../Modules/Class/app/Services/AttendanceRegister.php) calls it with the rows it saved, after its transaction. This covers both the admin panel and «کلاس‌های من».
- `class_attendances.absence_notified_at`; config `class.attendance.absence_sms`.
- `config/sms.php` gets `log_channel` (default `sms`). `config/logging.php` gets the channel `sms` (`storage/logs/sms.log`). `LogSmsSender` writes there.

## Consequences
- Guardians hear about each absence once, as soon as the teacher saves it.
- A wrong «غایب» corrected later is not followed by a correction SMS.
- With a real provider, sending happens during the teacher's request; if that is slow, move it to a queued job.
- Login codes in development are now in `storage/logs/sms.log`, not `laravel.log`.
