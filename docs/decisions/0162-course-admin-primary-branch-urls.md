# 0162 — Primary branch URLs for Course administration

- **Date:** 2026-11-27
- **Status:** accepted

## Context

The Course module manages installation-wide data. Its admin pages could open under any accessible panel branch, giving the same record several URLs.

## Options considered

- Keep Course pages available under every panel branch.
- Redirect branch-specific Course admin URLs to the primary branch.

## Decision

The Course resources and enrollment settings page use the primary branch’s admin URLs. A temporary redirect preserves the route, record identifiers and query string. Filament still authorizes access to the requested branch and to the primary branch. With no active primary branch, the middleware leaves the request to the normal panel and Course permission checks.

## Consequences

Course links for another branch open the same Course page in the primary branch. Source branches for bulk enrollment are chosen in the form. Public catalog and learner routes retain their existing URLs.
