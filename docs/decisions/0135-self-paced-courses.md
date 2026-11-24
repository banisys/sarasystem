# 0135 — Self-paced learning in the Course module

- **Date:** 2026-11-24
- **Status:** accepted

## Context

The legacy Course module combines a public course catalog with lessons, teaching profiles, enrollments and several commerce strategies. The local legacy database has four course rows, twelve activity rows and three user enrollment rows. SaraSystem already has branch membership, scheduled classes, online exams and private media storage.

## Options considered

- Extend scheduled classes with self-paced content. This would tie independent learners to timetables and class roles.
- Rebuild Course as a separate module. Branch members can learn on demand and the module can use the exam engine through its optional context contract.

## Decision

A branch owns its course categories, courses, sections, lessons, instructor assignments, enrollments and progress. An instructor is a branch member selected from the existing users. Branch-owned foreign keys include the tenant and, for lesson sections, the course. An education officer can manage courses in their current branch.

Each course has one optional catalog category. Publishing makes its introduction and syllabus visible on the branch's site. Members can sign in to preview designated lessons. Active enrollment is required for other lessons and for recording completion. Officers enroll members manually or allow free self-enrollment. A saved enrollment can have a start, an expiration or a revocation, and a self-enrollment request preserves those restrictions. The enrollment view shows the number of completed lessons. Completion belongs to the learner and lesson and is recorded explicitly.

Lessons can contain text, a private MP4 or WebM video, and downloadable attachments. The application's file action checks lesson access for every download and video request. Uploads use the current 12 MiB Livewire limit per file. Published courses have public URLs under `/courses` and `/{branch}/courses`. Opening a course in the user panel selects its branch only for a user authorized to enter it.

When Exam is enabled, its context contract exposes lesson exams. Course editors open the exam tools from the lesson's admin row. Active enrollees can take published lesson exams through the existing Exam flow, including practice, grading and results. Lesson exams are available on demand and retain the exam's per-attempt duration. The course and lesson remain usable with Exam disabled.

## Consequences

In the admin panel, «دوره‌های خودآموز» identifies on-demand learning and «ترم‌های آموزشی» identifies the calendar periods used by scheduled classes ([ADR 0022](0022-classes.md)).

Branch administrators control the availability and display names of supported enrollment methods through [ADR 0138](0138-course-enrollment-methods.md).

SaraSystem uses branches and user accounts for the organizational and instructor roles of the legacy Academy and Teacher records. Branch teacher profiles for those accounts were added in [ADR 0137](0137-course-teacher-profiles.md). Category trees were added in [ADR 0136](0136-course-category-trees.md). Paid and package enrollments, individual activity sales, related-course merchandising, gallery and site widgets, and inline video quizzes remain outside this port. Data import needs a separate mapping for branches, accounts and stored files.
