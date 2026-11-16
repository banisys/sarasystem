# 0103 — Default panel after login

- **Date:** 2026-11-16
- **Status:** accepted

## Context
The shared login ([ADR 0004](0004-authentication.md)) sent users to the user dashboard by default, even when they had access to the admin panel and typically signed in to work there.

## Options considered
- Keep the user dashboard as the default for everyone, so staff have to open the admin panel separately.
- Choose the default using existing panel access rules, while honoring a specific destination saved before login.

## Decision
Both password and SMS login preserve Laravel's intended destination. With no saved destination, users who can access the Filament panel and have an active admin tenant go to `/cpadmin`; everyone else goes to `/profile/dashboard`. Filament resolves the admin tenant at its existing entry route.

## Consequences
An Inertia login must open Filament destinations as full page visits, including when returning to an admin bookmark. Each destination still enforces its existing permissions.
