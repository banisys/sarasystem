# 0156 — Automatic lesson video duration

- **Date:** 2026-11-26
- **Status:** accepted

## Context

Staff previously entered a lesson's duration manually. The lesson syllabus already displays duration in whole minutes.

## Options considered

- Keep manual entry alongside the video upload.
- Read the uploaded file on the server and record the calculated duration.

## Decision

Remove manual duration entry from the lesson form. Use the Sail image's `ffprobe` to read an uploaded MP4 or WebM and round the duration up to the next whole minute for the existing `duration_minutes` field. Replacing the video recomputes duration. Removing it clears the value. An upload with unreadable duration produces a form validation error.

## Consequences

The app server needs `ffprobe` on its PATH. Existing values are preserved until the associated video changes. Video analysis runs against a temporary local copy, so the video can stay on the configured private disk.
