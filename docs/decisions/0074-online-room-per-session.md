# 0074 — Adobe Connect: a room per class or per session

- **Date:** 2026-11-08
- **Status:** accepted

## Context
[ADR 0073](0073-online-class-platforms.md) made one Adobe Connect meeting per class. The old sarayedanesh system made one per session. Each has its use: one room per class keeps a fixed address and the files, slides and layout from one session to the next; one per session keeps each session's recordings and Adobe attendance report apart, and gives access to that session only (a substitute is host of that session, not of the later ones). The user wants to choose when a class is made on Adobe Connect.

## Options considered
- **A setting of the server** — every class on a server works the same way; a school can't choose per class.
- **A choice of the class's room** — asked in the class form when the platform is Adobe Connect; a class moving to another platform mid-term (ADR 0073) chooses again for the new room.
- **Every session its own `class_online_rooms` row** — mixes «where the class meets from a day» with «the session's meeting on the platform».

## Decision
The room's choice: `class_online_rooms.per_session`. The class form shows «اتاق در Adobe Connect» («یک اتاق برای کل کلاس» / «یک اتاق برای هر جلسه», default per class) when the chosen server's platform offers it (`OnlinePlatform::offersRoomPerSession()`, only Adobe Connect). It can be chosen for a new class or when moving the class to another platform from a day; for the room the class already has it is shown disabled, since its sessions may already have meetings.

With a room per session, [AdobeConnectDriver](../../Modules/Class/app/Services/Online/AdobeConnectDriver.php) makes the session's meeting on its first entry, named «{class} ({code}) — {Jalali date} {time} #{session}», closed to guests, and keeps it in `class_online_session_meetings` (`ClassOnlineSessionMeeting`, per tenant, one per room and session; the room row is locked while it is made). Access and sign-in work as for a class's room. The class's «اطلاعات» says «(یک اتاق برای هر جلسه)» after the platform.

## Consequences
- Each session's recordings and attendance on Adobe are in their own meeting; a moved session keeps the name with its old day and time.
- Files and slides don't carry over to the next session, and the address changes every session (entry is through the system anyway).
- One more meeting on the server per session that someone entered; sessions nobody entered make none. Deleting a session deletes our row, not the meeting on Adobe.
- Changing the way for the room a class already has is not possible; move the class from a day instead (the same server can't be picked again from a day, since the same choice only updates the room).
