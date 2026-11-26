# 0157 — Private audio for course lessons

- **Date:** 2026-11-26
- **Status:** accepted

## Context

Course editors need an audio track alongside a lesson's other content and want the uploaded recording's duration in the database.

## Options considered

- Treat audio as an ordinary attachment.
- Add an optional audio track with inline playback and its own stored duration.

## Decision

Each lesson can have one private MP3, M4A, Ogg or WAV file up to 12 MiB. The shared server-side media probe records the audio duration in whole seconds, rounded up. Replacing the file recomputes the duration, and removing it clears the value.

## Consequences

The existing lesson-file access checks protect the audio player. Audio and video retain separate duration fields and can be present on the same lesson.
