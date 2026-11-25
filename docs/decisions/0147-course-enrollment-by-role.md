# 0147 — Course enrollment by branch role

- **Date:** 2026-11-25
- **Status:** accepted

## Context

Course editors can enroll accounts individually or enroll a class roster. Staff also need to enroll one role in a selected branch or across the organization.

## Options considered

- Make the branch role a standing condition for access to the course. Future role assignments and removals would grant and withdraw course access automatically.
- Create individual enrollments from the current role assignments when staff submit the action. Course staff retain the existing enrollment controls.

## Decision

The «ثبت‌نام بر اساس نقش» action uses the course update permission and the manual enrollment setting. A course editor can select branches where they can open the user directory. A super admin or someone with access to the primary branch's user directory can also select all branches, following the user directory's cross-branch access rule.

In a single branch, the action accepts shared roles and roles defined for that branch. The all-branches scope accepts a shared role and matches its assignments in every branch. This includes recorded assignments in inactive branches. Branch-specific roles with the same label remain separate roles. Selection follows the role ID and branch of the assignment.

The action adds one enrollment for each matching account with immediate access and no preset end date. It preserves every existing enrollment, including revoked or expired access. Class and role imports share the transactional enrollment logic and report new and existing counts.

## Consequences

One account holding a shared role in several branches is enrolled once. Changing a role assignment later has no effect on enrollments already created. Selecting a source branch does not change the current branch or the course's installation-wide ownership.
