# 0144 — Course participants page

- **Date:** 2026-11-25
- **Status:** accepted. [ADR 0145](0145-global-course-module.md) supersedes the original participant selection and permissions.

## Context

The legacy course list links to a page for viewing participants, adding users and removing course access. SaraSystem already lists and maintains course enrollments on the course edit page.

## Options considered

- Build a separate participant editor with legacy role and removal controls.
- Open the existing enrollment editor on a dedicated page so both entry points use the branch's registration settings and the same access rules.

## Decision

The «شرکت‌کنندگان» row action opens the selected course's enrollment editor. It lists all whole-course enrollments and lets course editors enroll users who belong to the current branch when manual enrollment is enabled. Start and end dates are optional. Enrollment grants course access to an existing branch member using their existing branch roles.

Editors revoke an enrollment through «لغو دسترسی» in its edit form. Keeping the enrollment records the revocation and prevents a free self-enrollment request from restoring access. Editors can restore access by clearing the revocation and adjusting the dates if needed.

## Consequences

The participants page and the course edit tab share enrollment records, search, completion counts and validation. Both use the selected branch and course update permission.
