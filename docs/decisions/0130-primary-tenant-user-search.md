# 0130 — Search users across branches from the primary tenant

- **Date:** 2026-11-24
- **Status:** accepted

## Context

Staff with access to the users list in the primary tenant need to find existing accounts across the organization. The users list ordinarily shows members of the selected branch.

## Options considered

- Add an opt-in filter to the existing users list.
- Create a separate organization-wide user directory.

## Decision

Add the «جستجو در همه شعبات» toggle to the existing filters for users with `ViewAny:User` in the primary tenant. The toggle expands the list to all accounts and works with the existing name, mobile, national code and email filters. It is off by default.

## Consequences

Staff can look up an account from the primary tenant without switching branches. Cross-branch results are read-only in this list. Record actions and edit routes continue to require membership in the selected branch and the existing action permissions.
