# 0139 — Self-paced course administration form

- **Date:** 2026-11-25
- **Status:** accepted. [ADR 0145](0145-global-course-module.md) supersedes the original code uniqueness, categories and instructor selection.

## Context

Course staff use the LMS creation form to enter general information, media, dates and prepayment details. SaraSystem already has branch-owned category trees, instructor assignments to user accounts, and manual and free registration.

## Options considered

- Keep the simplified SaraSystem form and add independent pages for each group of settings.
- Adopt the four sections from LMS in the course form and use SaraSystem's category tree, accounts, and existing enrollment controls.

## Decision

Creation and editing use the same four sections for general information, media, dates and pricing. The form omits audience gender and short description. Titles are required. Codes are unique within a branch and generated when left blank. New courses start published by default and the registration toggle still follows the branch's free-enrollment settings.

The first selected category remains the primary category, and a branch-scoped join table stores additional selections. The catalog filter matches any assignment, and category counts include all direct assignments. Categories are optional so installations without a taxonomy can create courses. The create form saves initial instructor assignments in the same database transaction as the course. New instructor assignments use the same branch membership and active-profile checks. The edit page displays the form only. Sections, lessons and participants are managed from their dedicated pages in the course row menu. Instructor selection is available when creating a course.

The editable rich description is stored as sanitized HTML. The existing reader views use a full plain-text version. Course cover, video cover, introductory video and ordered gallery are stored privately for staff editing, with a 100 MiB limit for introductory videos and a 12 MiB limit per image. The course uses the existing enrollment flow. Type, capacity, Jalali dates and prepayment are administrative fields. The dates are checked for order, and the prepayment amount is required when enabled.

## Consequences

Existing courses keep their primary category and publication state. Their descriptions open as text in the rich editor. The catalog and reader use their existing text presentation. Publishing introductory media, scheduling visibility or enrollment, capacity enforcement and payment or package registration can be implemented separately.
