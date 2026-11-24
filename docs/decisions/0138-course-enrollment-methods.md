# 0138 — Course enrollment methods by branch

- **Date:** 2026-11-24
- **Status:** accepted

## Context

The legacy enrollment settings page lists built-in strategies and lets administrators edit their names, availability, priority, and concurrency. SaraSystem already supports direct free enrollment and manual enrollment, with branch-owned courses and a single enrollment per learner and course.

## Options considered

- Configure one strategy catalog for the installation. A change there would affect all branches.
- Configure the supported methods per branch and keep the per-course free enrollment switch. Branches can maintain their own availability while preserving existing course choices.

## Decision

The «روش‌های ثبت‌نام» page lets users with course update permission set display names and availability for the branch's manual and free methods. It stores one settings row per configured branch. Until a branch saves settings, both methods retain their existing behavior.

The manual method controls new staff-created enrollments. The free method controls the offer in the public catalog and learner panel and is checked again when a learner submits the enrollment request. A course must also be published and allow free enrollment. Staff can continue maintaining existing enrollment records.

With both methods disabled, registration is closed. Payment and package strategies remain deferred under [ADR 0135](0135-self-paced-courses.md). Strategy priority and concurrency are unnecessary for the independent manual and free flows.

## Consequences

Branches can pause registration without changing course settings, enrollment dates, revocation state, or access already granted. The page uses branch context and the existing course management permission. Rolling back the migration drops branch enrollment preferences.
