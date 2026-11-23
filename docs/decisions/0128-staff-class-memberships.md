# 0128 — Class memberships in the user panel

- **Date:** 2026-11-23
- **Status:** accepted

## Context

Teachers, supervisors and other class staff need a place to see their classes, each class's description and its roster.

## Options considered

- Expand the class cards embedded in the calendar with member lists.
- Keep the calendar focused on sessions and give class memberships their own navigation entry.

## Decision

Add «کلاس‌های من» at `/profile/my-classes` for non-learner accounts. Accounts with the learner role in the current branch and accounts whose current class memberships are all attendee roles keep their calendar class cards. Guardians' ward calendars also keep their cards.

List classes in the current branch where the signed-in user is a current member. Show the class description and the names and class roles of current members, including learners. Each class has an expandable roster. Calendar filtering still uses the same classes. Legacy calendar view URLs with parameters and session URLs still redirect to the calendar.

## Consequences

Teaching assignments still appear on the calendar. A user needs a class membership for that class to appear on the membership page. Rosters expose member names and class roles.
