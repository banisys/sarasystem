# 0146 — Course enrollment from a class

- **Date:** 2026-11-25
- **Status:** accepted

## Context

Staff can manually enroll one user in a self-paced course. When a class needs the same course, staff need to enroll its current attendees together. Courses are installation-wide and class rosters belong to a branch.

## Options considered

- Link classroom membership directly to course access. That would make future membership changes grant or withdraw course access automatically.
- Copy the current attendee roster into ordinary enrollments when staff submit the action. Course access stays under the course's existing enrollment controls.

## Decision

The «ثبت‌نام از کلاس» action in the enrollment editor requires an explicit source branch, then lists classes from that branch. The source picker offers active branches where the editor has panel access and permission to browse classes. Changing the source clears the class selection and preserves the active panel branch. It requires course update permission, access to the class roster and enabled manual enrollment. It adds only attendees whose membership is in effect on the day of submission. Access starts immediately and has no preset end date. Existing enrollments, including expired and revoked records, keep their saved state. The action reports the number of new and existing enrollments.

The Class module supplies an authorized roster through the [ClassRoster](../../app/Support/ClassRoster.php) contract. The enrollment editor shows the action only when the source is installed and enabled. Course enrollments keep no reference to the originating class.

## Consequences

The same action can be retried without creating duplicates. Class remains responsible for branch visibility and attendance roles. Edits to the class roster have no effect on registrations already added to the course.
