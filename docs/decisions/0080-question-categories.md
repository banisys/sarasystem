# 0080 — Hierarchical question categories

- **Date:** 2026-11-11
- **Status:** accepted

## Context

Exam managers need a subject hierarchy to organize questions and find them across banks. Existing exams use bank membership to draw questions. Tags are a separate phase.

## Options considered

- **One optional category per question** gives every classified question one place in the hierarchy and lets existing questions keep a null assignment. A many-to-many category pivot would support several placements but makes category ownership and browsing more complex.
- **A parent reference per category** allows moving a whole branch with one update. Materialized paths would simplify querying descendants in SQL but would require rewriting descendant paths on every move.

## Decision

- Each tenant manages its tree at «آزمون → دسته‌های سؤال». [The category page](../../Modules/Exam/app/Filament/Resources/QuestionCategories/Pages/ListQuestionCategories.php) shows nested branches styled like the Site menu editor ([ADR 0012](0012-site-menus.md)), with direct question counts and per-row actions. Search keeps matching branches in context. Create and edit use the same modal form, with full paths in the parent selector. The row action «افزودن زیردسته» preselects the parent; editing a category's parent moves the branch.
- A question may select a category at any depth. The question list displays its full path, and the category filter includes all descendants. «بدون دسته» finds uncategorized questions. The existing banks still define exam sections and question selection.
- Composite foreign keys enforce same-tenant parent and question links. [QuestionCategory](../../Modules/Exam/app/Models/QuestionCategory.php) serializes writes that assign a parent in the same tenant and checks ancestors under the lock to reject cycles. The database refuses to delete a category that still has questions or children.
- The panel uses `*:QuestionCategory` permissions. The education officer and the roles above it receive the standard category permissions through the default role installer. The school tutorial teaches creation, assignment and filtering only when Exam is enabled.

## Consequences

- Existing questions start uncategorized. Classification changes neither bank memberships nor past attempt snapshots.
- Moving a branch updates the displayed paths and the results of parent filters immediately. To delete a category, first move its subcategories and move or clear its question assignments.
- The category picker and filter load the current tenant's taxonomy in one query. Filtering computes the branch in memory and applies its IDs to the paginated question query.
