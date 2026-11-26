# 0135 — Self-paced learning in the Course module

- **Date:** 2026-11-24
- **Status:** accepted. [ADR 0145](0145-global-course-module.md) supersedes the original course ownership and branch-dependent access.

## Context

The legacy Course module combines a public course catalog with lessons, teaching profiles, enrollments and several commerce strategies. The local legacy database has four course rows, twelve activity rows and three user enrollment rows. SaraSystem already has branch membership, scheduled classes, online exams and private media storage.

## Options considered

- Extend scheduled classes with self-paced content. This would tie independent learners to timetables and class roles.
- Rebuild Course as a separate module. Branch members can learn on demand and the module can use the exam engine through its optional context contract.

## Decision

A branch owns its course categories, courses, sections, lessons, instructor assignments, enrollments and progress. An instructor is a branch member selected from the existing users. Branch-owned foreign keys include the tenant and, for lesson sections, the course. An education officer can manage courses in their current branch.

Each course can have an optional primary catalog category and additional categories ([ADR 0139](0139-course-admin-form.md)). Publishing makes its introduction and syllabus visible on the branch's site. Members can sign in to preview designated lessons. Active enrollment is required for other lessons and for recording completion. Officers enroll members manually or allow free self-enrollment. A saved enrollment can have a start, an expiration or a revocation, and a self-enrollment request preserves those restrictions. The enrollment view shows the number of completed lessons. Completion belongs to the learner and lesson and is recorded explicitly.

Lessons can contain text, one private MP4 or WebM video, [one private audio file](0157-course-lesson-audio.md), and up to five downloadable attachments ([ADR 0155](0155-course-lesson-content-and-exam-code.md)). The application's file action checks lesson access for every download and video request. Lesson video and attachment fields limit uploads to 12 MiB per file. Published courses have public URLs under `/courses` and `/{branch}/courses`. Opening a course in the user panel selects its branch only for a user authorized to enter it.

When Exam is enabled, its context contract exposes lesson exams. Authorized course editors [link an existing exam by code](0160-link-original-lesson-exam.md). The lesson row opens the exam management tools. Active enrollees can take published lesson exams through the existing Exam flow, including practice, grading and results. The exam keeps its availability settings and per-attempt duration. The course and lesson remain usable with Exam disabled.

## Consequences

In the admin panel, «دوره‌های خودآموز» identifies on-demand learning and «ترم‌های آموزشی» identifies the calendar periods used by scheduled classes ([ADR 0022](0022-classes.md)).

The «سرفصل‌ها» and «درس‌ها» actions on each course row open dedicated admin pages. They use dedicated section and lesson editors and require permission to update that course in the current branch. The lessons route is `/{record}/lessons` under the course resource ([ADR 0151](0151-course-lessons-route.md)).

The «شرکت‌کنندگان» row action opens the course's enrollment editor for branch members, with access dates and revocation ([ADR 0144](0144-course-participants-page.md)).

Branch administrators control the availability and display names of supported enrollment methods through [ADR 0138](0138-course-enrollment-methods.md). The course form and its administrative details are described in [ADR 0139](0139-course-admin-form.md).

SaraSystem uses branches and user accounts for the organizational and instructor roles of the legacy Academy and Teacher records. Branch teacher profiles for those accounts were added in [ADR 0137](0137-course-teacher-profiles.md). Category trees were added in [ADR 0136](0136-course-category-trees.md). Paid and package enrollments, individual activity sales, related-course merchandising, gallery and site widgets, and inline video quizzes remain outside this port. Data import needs a separate mapping for branches, accounts and stored files.
