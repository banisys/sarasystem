# 0126 — Academic term in the user panel menu

- **Date:** 2026-11-23
- **Status:** accepted

## Context

The menu needs to show the organization’s current teaching period below the account identity. The Class module owns terms and can be disabled.

## Options considered

- Choose the term from the user’s classes, which can span multiple periods or be empty.
- Use the organization’s active term whose dates include today and supply its title through the shared panel.

## Decision

Class provides the term title through the user panel’s `navigationSubtitleUsing()` hook. The title comes from the primary tenant’s catalog. When periods overlap, the latest start date wins, with the newest record breaking ties.

## Consequences

The same organization term appears across branches in the sidebar and mobile drawer. The subtitle is omitted when there is no current term or when Class is disabled.
