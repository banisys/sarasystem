# 0143 — Session gallery

- **Date:** 2026-11-25
- **Status:** accepted

## Context

Class staff need a place to keep photos and clips documenting a session. The session page already separates teaching materials from staff reminders and learner submissions.

## Options considered

- **Reuse session materials.** This shares their upload flow, but mixes gallery files with handouts and ties uploads to content recording.
- **Use a separate session media collection.** This keeps gallery files together and allows one upload permission for staff.

## Decision

The «گالری» tab stores images and videos in a dedicated Media Library collection on the private disk. The assigned teacher, substitute, and any active class member with a non-attendee role may upload. The same session and branch authorization applies when opening a gallery file, including read-only access through a learner or guardian view. The file route supports inline playback and checks authorization for each request.

## Consequences

Uploads accept JPEG, PNG, GIF, WebP, MP4, and WebM within the configured Media Library file size limit. The gallery displays original files and leaves attendance and teaching content recording to their respective tabs. Adding a file marks the session as modified for schedule rebuilding. Gallery files follow the session's lifecycle.
