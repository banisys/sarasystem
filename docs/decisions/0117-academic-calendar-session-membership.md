# 0117 — Session membership in the academic calendar

- **Date:** 2026-11-21
- **Status:** accepted

## Context

The user-panel calendar included all sessions of a class once the user had any membership record. This showed sessions before they joined and after they left. The user asked to see only the sessions they belong to.

## Options considered

- **A — Use memberships current today.** Simple for future sessions, but drops the user's history after they leave and includes sessions they missed before joining.
- **B — Check membership on the session date.** Keeps the user's history and follows the session's own teaching assignments when a substitute is scheduled.

## Decision

Use B. The day, week and month views include teaching and exam sessions in the selected tenant where the person held a class role on the session date or is assigned to teach that session. Both membership boundary dates are inclusive. A missing boundary leaves that side of the membership open. When a substitute takes a teaching assignment, the substitute qualifies through that assignment. A guardian's calendar uses the ward's memberships and assignments after verifying access to the ward.

## Consequences

The filter is applied by [MyClasses](../../Modules/Class/app/Services/MyClasses.php) when building calendar data. Existing access to session detail links and the rules for recording work on a session are independent of this filter.
