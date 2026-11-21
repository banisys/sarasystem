# 0117 — Session membership in the academic calendar

- **Date:** 2026-11-21
- **Status:** accepted

## Context

The user-panel calendar included all sessions of a class once the user had any membership record. This showed sessions before they joined and after they left. The user asked to see only the sessions they belong to.

## Options considered

- **A — Use memberships current today.** Simple for future sessions, but drops the user's history after they leave and includes sessions they missed before joining.
- **B — Use the session's attendance roster and teaching assignments.** Keeps the user's history while limiting the calendar to sessions they participate in.

## Decision

Use B. The day, week and month views include teaching and exam sessions in the selected tenant where the person is on the attendance roster or is the assigned teacher, substitute or exam invigilator. The roster includes class members with an attendee role on the session date and people with recorded attendance for that session. Membership boundary dates are inclusive and a missing boundary leaves that side open. A class-level staff role on its own does not put sessions on the calendar. A guardian's calendar applies the same rules to the ward after verifying access.

## Consequences

The filter is applied by [MyClasses](../../Modules/Class/app/Services/MyClasses.php) when building calendar data. Existing access to session detail links and the rules for recording work on a session are independent of this filter.
