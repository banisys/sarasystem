# 0166 — Browse all published courses in the user panel

- **Date:** 2026-11-27
- **Status:** accepted

## Context

Users in non-learner roles need to browse the installation-wide course catalog from «دوره‌های من», including courses in which they have no active enrollment.

## Options considered

- Link to the public catalog. Users would leave the panel to browse and return to open course details.
- Add a switch to «دوره‌های من». Both lists use the same course cards and pagination in the panel.

## Decision

The controls below the page header include a title search for everyone and a switch between active enrollments and all published courses for eligible users. Search applies to the selected list before pagination. The URL keeps the selected view and search term across pages of 15 courses. Eligibility follows the user's roles in the current branch. A learner role suppresses the switch, while installation-wide super admins can always use it.

## Consequences

Eligible users can browse courses and see their [covers](0163-my-courses-cover-images.md) without an enrollment. Cards report the user's actual enrollment. Private lessons retain their enrollment checks.
