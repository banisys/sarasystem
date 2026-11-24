# 0087 — Seating plans for exam sessions

- **Date:** 2026-11-12
- **Status:** accepted

## Context
Class editors need to place attendees on specific seats before an exam and see that arrangement in the exam's 3D room. Attendance is already recorded by person.

## Options considered
- Store a seating plan on each exam session. A bounded plan can be saved together and needs no separate room inventory.
- Store seats and assignments in separate tables. This suits reusable physical rooms, but requires more setup and a room model the project does not have.

## Decision
Store a nullable JSON plan on the tenant-owned session: rows, columns and a flat list of attendee IDs or empty slots. The first seat is at the front right. The editor uses the exam-day attendance roster, accepts manual placement and swapping, and can fill the current grid in roster order. It can also clear all assignments at once while keeping the grid dimensions. Leaving gaps is supported. The server checks the grid dimensions, rejects duplicate people and requires every assignment to belong to the current roster. Admin-panel saves require classroom edit permission. Cancelled exams cannot be edited.

The user-panel exam session details show «چیدمان صندلی‌ها» to class editors and effective invigilators who can open the session ([ADR 0132](0132-invigilator-seat-layout-access.md)). The editor shares the seating plan, attendance roster and validation rules with the admin panel. Guardian views do not offer editing.

## Consequences
The editor highlights the seat under the pointer with a soft red outline and fill while an attendee is dragged from the unassigned list or from another seat.

The plan is saved independently of attendance, so an attendance status follows the person when moved. If a person leaves the roster, their slot appears empty; new attendees appear in the unassigned list. The 2D editor works without Campus. When Campus is enabled, the 3D scene uses the saved grid and shows empty seats; attendees without assigned seats remain in the accessible list and count. Without a saved plan, the scene uses the previous symbolic arrangement.
