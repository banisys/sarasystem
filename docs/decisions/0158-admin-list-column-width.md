# 0158 — Admin list column width

- **Date:** 2026-11-26
- **Status:** accepted

## Context

Long values in admin lists can make a single column take up most of the table.

## Options considered

- Assign fixed widths to individual columns.
- Apply a shared maximum and allow each column to size to its content.

## Decision

Admin resource lists occupy at least the width of their container. Column content has a shared maximum of 20rem and long text wraps within that limit. Lists with fewer columns distribute spare width across the cells.

## Consequences

Tables with many columns can still scroll horizontally.
