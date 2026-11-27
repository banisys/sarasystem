# 0163 — Course covers in My Courses

- **Date:** 2026-11-27
- **Status:** accepted

## Context

Learners need a visual cue to recognize courses in «دوره‌های من». Staff already upload course covers to private storage.

## Options considered

- Publish cover copies to the public disk. This would let the browser load them directly and require managing copies when covers change.
- Serve the existing cover through the user panel. This reuses staff uploads and checks access on each request.

## Decision

Course cards request the existing cover through the authenticated course route. The route serves supported image formats for published courses when the viewer has an active enrollment or is eligible to [browse all published courses](0166-profile-course-catalog-toggle.md). The card displays the cover above the course details.

## Consequences

Existing covers work without moving files or changing the upload form. Courses without a cover display a text card. Changes in publication, enrollment, or catalog eligibility apply to the next image request.
