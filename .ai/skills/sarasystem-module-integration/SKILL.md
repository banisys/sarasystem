---
name: sarasystem-module-integration
description: Add or review SaraSystem modules and features that connect optional modules. Use when choosing an integration hook, declaring a required module, or verifying that features work with an optional module disabled.
---

# SaraSystem module integration

Start with the [module conventions](../../../docs/conventions.md#independent-modules-adr-0041) and [ADR 0041](../../../docs/decisions/0041-independent-modules-per-feature-licensing.md). Use the [module creation conventions](../../../docs/conventions.md#creating-a-module-adr-0005) when scaffolding a module.

1. Identify what should still work when either optional module is disabled. Look for an existing contract, event or registry owned by the core or by the module being extended. For examples, see [ModelRelationManagers](../../../app/Filament/Support/ModelRelationManagers.php) for resource extensions and [ClassroomExtensions](../../../Modules/Class/app/Support/ClassroomExtensions.php) for Class integrations.
2. Follow the extension from service-provider registration to the consuming page, job or action. Check that the optional module's tables, routes and classes are only used when that integration is available. If the feature inherently requires another module, follow the `requires` decision and documentation workflow in the conventions.
3. Verify behavior with the integration enabled and run the disabled-module check from the conventions in an isolated development or test checkout. [Module status](../../../docs/decisions/0042-module-manager.md) is persisted in `modules_statuses.json`. Record the starting state and restore it after the check, including if a test fails. Cover a remaining page or route that normally uses the integration.
