# 0136 — Branch course category trees

- **Date:** 2026-11-24
- **Status:** accepted

## Context

Course staff need the same category management layout as the question bank, along with the title, alias, parent and short description used by the LMS course category form. The legacy data model also carries ordering, activation, language, a translation key and custom attributes.

## Options considered

- Build a separate tree inside Course, keeping the question category layout as a visual reference.
- Share the category tree, actions and parent validation through the core, letting each module supply its own fields, query, permissions and counts.

## Decision

Use the same tree view for Course and Exam. Course supports alias search. Tree rows display the title, direct course count and inactive status. Staff can create a root or child, move a branch by selecting a parent and set sibling order with a numeric position. Composite foreign keys and the same tenant-locked ancestor check used for questions protect parent assignments. The delete action requires staff to move any children and detach or move assigned courses first.

The course category form stores title, alias, parent, short description, display position, and active status. Existing locale, translation key, and JSON attributes are retained in storage. The URL-selected branch supplies the legacy instance. The root is derived from the parent chain. Alias is unique within the branch and is suggested from the title when blank. Existing categories retain their assignments and become active roots. An existing category with an empty alias gets a title-based suggestion when opened for editing.

Course editors use the tree to assign one category per course. The catalog's category filter includes active descendants and omits inactive branches from its options. Unfiltered published courses remain visible.

## Consequences

Neither module requires the other to manage categories. The legacy drag control maps to numeric display position and the parent picker. The categories and their metadata are managed independently in each branch.
