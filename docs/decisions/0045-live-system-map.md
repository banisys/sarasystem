# 0045 — Live system map with module-owned descriptions

- **Date:** 2026-11-03
- **Status:** accepted

## Context
The admin panel needs a visual overview of the system and its relationships that stays useful as features are developed. A pasted diagram would drift from registered pages and installation-specific module switches.

## Options considered
- **Static Mermaid/image with a regeneration command:** convenient to export, but adds a snapshot that can drift and a rendering dependency.
- **Infer everything from routes/classes:** discovers endpoints, but cannot reliably explain business relationships or audience-facing groupings.
- **Live discovery plus module-owned semantic metadata (chosen):** automatic structural coverage, explicit business meaning, no new runtime package.

## Decision
The core provides a super-admin-only `SystemMap` page implementing `IndependentOfTenant`. Every page load reads installed modules through `ModuleManager`, plus registered and authorized Filament navigation resources/pages. A full reload is the regeneration action; no generated artifact is persisted.

Each module's optional `sitemap` manifest object supplies a Persian label, category, interface surfaces and directed business connections. Missing metadata has a safe generic fallback. Disabled modules stay visible as unavailable cards, without page links or active business connections. Technical dependencies continue to come exclusively from `requires`.

Responsive Blade/CSS renders the hierarchy, expandable destinations and labeled business arrows without external scripts. Core summaries remain curated. Details are in [system-map.md](../system-map.md).

## Consequences
New modules/pages appear without editing a central sitemap. Changes in business meaning still require a small manifest edit during development. Screen links reflect current tenant authorization even though the structural overview is independent of tenant data. This is an installation overview, not a complete route catalog, database diagram or exported documentation snapshot.
