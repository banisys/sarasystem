# 0131 — Actions on cross-branch user search results

- **Date:** 2026-11-24
- **Status:** accepted

## Context

The [primary tenant's user search](0130-primary-tenant-user-search.md) finds accounts in other branches. Staff need to act on those results while honoring each branch's permissions.

## Options considered

- Authorize all cross-branch actions using the operator's primary-tenant role.
- Use the operator's existing permissions in each account's branch.

## Decision

Keep direct edit routes scoped to members of the branch in the URL. For a result outside the primary tenant, edit links to the first branch by name where the operator has admin access and permission to edit the account. Membership removal requires selecting an authorized branch and removes roles only in that branch. Account deletion requires edit and delete rights in an eligible branch or installation-wide super-admin access.

Impersonation follows [ADR 0008](0008-impersonation.md). Leaving impersonation returns the operator to the filtered list.

## Consequences

The search provides a shortcut to existing management actions. An operator without access to an account's branch can find it in the list but has no branch-management actions there.
