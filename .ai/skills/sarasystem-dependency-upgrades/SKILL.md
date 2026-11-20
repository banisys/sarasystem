---
name: sarasystem-dependency-upgrades
description: Assess or carry out Composer and npm dependency updates for SaraSystem. Use when checking upgrade risk, planning compatible package batches, or validating changed PHP and JavaScript lockfiles.
---

# SaraSystem dependency upgrades

Use the Sail commands in [AGENTS.md](../../../AGENTS.md). For a risk assessment, inspect manifests and lockfiles and use dry runs. Apply updates when the user requests them, in a separate checkout if there is active unrelated work.

1. Read [composer.json](../../../composer.json), the Composer manifests under `Modules/`, and [package.json](../../../package.json). Check the PHP and Node versions in the target environment. Start with the existing version constraints unless the requested upgrade requires changing them. Preview broad updates with `composer update --dry-run --no-scripts` and `npm update --dry-run --ignore-scripts`. Read release notes for the proposed versions and report incomplete results when registries are unreachable.
2. Review dependency diffs in batches that can be verified and reverted together. Check Filament with its plugins, Inertia across Laravel and Vue, and Vite with its Vue, Laravel and Tailwind plugins. If Composer or npm cannot resolve a batch, identify the actual constraint or peer dependency before changing ranges. Use the resulting lockfiles to reproduce the installation.
3. Run normal Composer scripts for the real installation so Filament's assets are republished. Check the resulting PHP platform requirements with `composer check-platform-reqs --lock`. Run `npm run build` for frontend or Filament theme changes. Follow the [test policy](../../../docs/conventions.md#testing) for shared dependency changes. For affected browser flows, check branch switching, admin menus and modals, or Inertia form submission and navigation as appropriate.
4. Review the final manifest and lockfile diffs and report unresolved audit, install, test, or build results. Keep dependency changes in their own batch so reverting the commit and reinstalling the prior locked versions restores the dependency set.
