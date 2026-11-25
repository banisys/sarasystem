# 0145 — Installation-wide self-paced courses

- **Date:** 2026-11-25
- **Status:** accepted

## Context

The first release of Course gave every course, category, teacher profile, enrollment and lesson completion a branch owner. The organization needs one course catalog that users can access independently of their branch assignments. Staff authorization and the question library still use the platform's existing roles and Exam engine.

## Options considered

- Keep branch ownership and show selected courses across branches. This would preserve separate catalogs, rosters and registration settings.
- Move Course records to installation-wide ownership. Staff manage one catalog and rosters, and learners keep their progress when they change branches.

## Decision

Courses, sections, lessons, categories, instructor assignments, participants, completion and registration methods are installation-wide. The public catalog is at `/courses`. Old branch catalog and course-entry links redirect to the shared location. Authenticated accounts can enroll or study without choosing a branch. Publishing, registration settings, effective enrollment dates, revocation and private file checks keep their existing meanings.

Super admins and staff granted Course permissions in the primary branch can manage these records. Branch assignments outside the primary branch confer no Course permissions. The central permissions apply regardless of the branch displayed in the Filament URL, subject to normal panel access to that URL. The tenant selector labels the Course pages «مستقل از شعبه».

Lesson exams use Course enrollment to authorize entry from the lesson. The Exam module stores new contexts and their banks in the primary branch. The hosting tenant applies to an authorized exam request and does not change the selected branch. Existing exams retain their original Exam storage and attempts. A lesson with existing exams continues using that storage for the context. Standalone exams, session exams, the question library and the general Exam index retain their branch rules.

## Consequences

The migration preserves record IDs and relationships for courses, lessons, enrollment, progress and assignments. Duplicate titles, aliases and codes gain numeric suffixes after the first occurrence. If there are several settings rows or teacher profiles for one account, the primary-branch record takes precedence, followed by the most recently updated record when no primary record exists. The migration removes superseded copies and cannot recover branch ownership after installation-wide writes. Restore a pre-migration database backup to roll back.

Course remains usable with Exam disabled. The authority to manage the course catalog and participant lists is evaluated from the installation's current primary branch. The shared catalog can still open if the installation has no primary branch.
