# 0113 — Put admin sidebar icons on navigation groups

- **Date:** 2026-11-20
- **Status:** accepted

## Context
The LMS sidebar uses one icon per navigation group and bullets for its links. SaraSystem already derives its navigation from the pages and resources available to the current user.

## Options considered
- Register an explicit list of icon-bearing groups. Filament would use the registration order ahead of the order derived from visible items.
- Configure the groups Filament creates. This keeps group discovery, visibility and ordering with the existing navigation manager.

## Decision
Set icons on named admin navigation groups when Filament creates them. Resolve the learners group from the installation's configured label. Filament renders the group icons and expanded sidebar bullets. The admin theme styles links in collapsed group dropdowns with the same bullet colors.

## Consequences
Named groups get a group icon in the collapsed sidebar, with their links in a dropdown. Standalone links keep their own icons. Filament continues to discover and order the links users can access.
Sidebar search filters the expanded tree. Collapsing the sidebar shows the group dropdowns even while a search query is retained.
