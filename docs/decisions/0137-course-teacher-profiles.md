# 0137 — Course teacher profiles per branch

- **Date:** 2026-11-24
- **Status:** accepted

## Context

The LMS teacher directory records education, institution, teaching experience and biographies. SaraSystem already models named instructors as course assignments to existing users. One person can teach multiple courses and belong to multiple branches.

## Options considered

- Store the teaching details on each assignment. That would duplicate a teacher's biography and qualifications across courses.
- Store a separate profile for each user and branch. Existing assignments continue to identify the user, and the profile supplies the directory information.

## Decision

Course adds «مدرسان دوره» to the branch admin panel. Education officers can manage one profile per user in the branch. The profile includes education level, field of study, institution, numeric teaching experience, short and full biographies, and active status. The directory shows the count of existing course assignments in that branch. The profile's linked account is fixed after creation.

Assignments continue to use branch members' existing accounts. An inactive profile prevents new assignments in that branch. Existing assignments remain visible and deleting a profile leaves the user and assignments intact. Branch members can be assigned without a profile.

## Consequences

Adding teaching details does not create another login or grant a role. The directory works with Course alone. The public course details still show assigned instructors' names. Profile biographies are maintained in the admin directory.
