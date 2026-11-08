# 0068 — Several teachers per schedule and session

- **Date:** 2026-11-08
- **Status:** accepted (changes the teacher of [ADR 0023](0023-class-schedules-and-sessions.md))

## Context
A schedule and each of its sessions had a single teacher (`teacher_id`), and a session could have one substitute (`substitute_teacher_id`) who taught instead of that teacher. Some classes are taught by two or more people at once, for example a language class with a native-speaker teacher, or a lab with a teacher and an assistant. The user asked to be able to pick several teachers for a session.

## Options considered
- **Where:** on schedules and sessions / **only on single sessions.** If only single sessions had several teachers, the extra teachers of a recurring class would have to be added to every session by hand.
- **Roles:** **all teachers equal** / one main teacher with assistants. Assistants would need another kind of rights and a main-teacher rule for the score book.
- **Substitute:** **stands in for one teacher** / editing the list is the substitution. With an edited list, nothing would record who replaced whom, and a teacher's calendar couldn't say «جایگزین».
- **Storage:** **rows in pivot tables** / arrays of ids in JSON columns. Rows are indexed and easy to query («who actually teaches» is `coalesce(substitute_id, teacher_id)`); JSON arrays would be hard to query for clashes and calendars.

The user chose the first option of each question (2026-11-08).

## Decision
- `class_schedule_teachers (schedule_id, user_id)` holds a schedule's teachers.
- `class_session_teachers (session_id, teacher_id, substitute_id)` holds a session's teachers, each with an optional substitute.
- The old columns are dropped. The migration moves their data: a substitute on a session that had no teacher becomes its teacher.
- The rows have no model and no `tenant_id`: they belong to their schedule / session, which is tenant-owned, and the cross-tenant teacher clash check needs to read them anyway.
- **Schedule:** «مدرس‌ها» is a multi-select of the class's non-attendee members. Saving gives them to every untouched future session.
- **Session:** a make-up / extra session takes several teachers. On an existing session, «مدرس‌ها و جایگزین» (`substitute` action, `SessionPlanner::setTeachers()`) lists its teachers, each with a «جایگزین» select (anyone in the tenant), and can add or remove teachers.
  - Changing the list sets `is_modified`, so rebuilding the schedule leaves the session alone.
  - A substitute does the same through `isUntouched()`.
  - One person may appear only once among those who teach a session.
- **All teachers are equal:** each one takes attendance and records content (`SessionAccess`), and sees the session in «کلاس‌های من» and in their teaching calendar (`ClassSession::taughtBy()`: teachers nobody replaces, and substitutes). Each one enters the subject's scores (`ScoreBook`: any teacher of a schedule of the subject). A replaced teacher still sees the session in «کلاس‌های من», as before.
- **Clashes** are checked for everyone who actually teaches, and the message names the busy teacher.
- **Display:** lists, the calendar and «کلاس‌های من» show e.g. «رضا کریمی، سارا رضایی (جایگزین مریم احمدی)». The 3D view draws a figure for each teacher.

## Consequences
- More than one person can take a session's attendance; the last save wins, as with a teacher and a supervisor before.
- Setting a session's own teachers is all or nothing per session; there is no "add this teacher to all future sessions" except editing the schedule.
- Going back (`migrate:rollback`) keeps only the first teacher of each schedule and session.
- The tutorial and sample-data rows of these tables aren't recorded, but they are deleted with their schedule / session.
