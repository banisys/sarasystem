# 0150 — Course introduction video upload limit

- **Date:** 2026-11-26
- **Status:** accepted

## Context

Staff need to upload introductory videos of up to 100 MiB. Livewire validates each upload before the course form receives it, and PHP applies its own limits to the file and multipart request.

## Options considered

- Change only the Filament field. Livewire would still reject a video over 12 MiB before the form validated it.
- Align the existing upload layers. This keeps the signed temporary upload flow and lets the form enforce its video-specific rules.

## Decision

Accept MP4 or WebM introduction videos up to 100 MiB in the course form. Set Livewire's shared temporary upload ceiling to 100 MiB and local Sail's PHP limits to 100 MiB per file and 110 MiB per POST. The extra request space accommodates multipart encoding. Individual fields still apply their own type and size rules when saving.

## Consequences

Any separate deployment needs its PHP and HTTP request limits to accommodate the same multipart upload. Introduction videos continue to use the course's private storage.
