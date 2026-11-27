# 0165 — Share course lessons in class sessions

- **Date:** 2026-11-27
- **Status:** accepted

## Context

Class staff need to share individual lessons in a session's «مطالب و تکلیف» tab. A learner on the session roster should be able to read or download the assigned content without joining the self-paced course.

## Options considered

- Enroll class members in the course and send them links to its lessons.
- Attach references to selected lessons to the session and scope access to the session roster.

## Decision

A session's teacher or substitute and its class's current non-attendee staff can enter one or more [lesson codes](0164-course-lesson-codes.md), one per line. The separate save action replaces the ordered list in the session and accepts lessons from published courses. Class stores stable lesson IDs, so renaming a code does not break an existing share. An unknown or unpublished code leaves the saved list unchanged. Saving the list does not change whether the session has been held.

Staff and learners on the session roster can open shared text, video, audio and attachments inside «مطالب و تکلیف». The existing guardian view follows the ward's roster access and stays read-only. Every file request resolves the session in the current branch, rechecks access, and verifies that the requested file belongs to the assigned lesson. Sharing provides no course enrollment, exam access or progress actions. Exam and cancelled sessions do not expose shared lessons.

## Consequences

Course supplies content through the optional [session lesson library](../../app/Support/SessionLessonLibrary.php). The modules have no cross-module foreign key or required dependency. When Course is disabled, the sharing controls and files are unavailable and the session retains its lesson references. Deleted or unpublished lessons are omitted. A future session with shared lessons is preserved when its schedule is rebuilt.
