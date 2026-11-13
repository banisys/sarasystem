# 0085 — Exam invigilators across tenants

- **Date:** 2026-11-12
- **Status:** accepted

## Context
Class editors may assign employees from any branch to supervise an exam sitting.

## Options considered
- Use class members as before. An employee would need to join the class before being assigned.
- Use the organization-wide employee directory with the existing session staffing relation. This keeps the existing session actions and clash checks available.
- Add a separate invigilator relation. This would need a second path for clash detection, session display and staff access.

## Decision
Use the existing session staffing relation with a separate eligibility rule for exam scheduling. The optional [EmployeeDirectory](../../app/Support/EmployeeDirectory.php) contract exposes users with employment records from any tenant. Only class editors can search and assign these users through the schedule form. The picker shows each employee's national code in parentheses after their name when available, so employees with the same name can be distinguished. A user with several employment records appears once. The Employee module provides the directory. Class validates selections against it when saving.

## Consequences
Invigilators use the same clash rules and session staff behavior as existing session assignments. Scheduling without invigilators works with Employee disabled. Teaching schedules still use class membership to determine eligibility.
