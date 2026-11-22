# 0114 — Store project skills in the Codex directory

- **Date:** 2026-11-20
- **Status:** accepted

## Context
SaraSystem used the Claude Code layout for Laravel Boost skills. The user wants the Codex project layout used by LMS.

## Options considered
- Move the skills alone. A later Boost update would still target the old directory.
- Move the skills and select Codex in Boost. The generator will manage the new paths directly.

## Decision
Move all existing skills to `.agents/skills/` and set `codex` as the Boost target in `boost.json`. Boost updates the generated guidelines through the existing `AGENTS.md` entry point.

## Consequences
Codex discovers skills in `.agents/skills/`. SaraSystem's custom skills are authored in `.ai/skills/`, and Boost creates relative links to them in the Codex directory. The Filament skill comes from the installed Filament package selected by `boost.json`. The existing `AGENTS.md` symlink still points to `CLAUDE.md`, so both instruction paths share the same file.

[Element Plus Skills](../../.agents/skills/element-plus-skills/SKILL.md) is installed directly in the project skill directory from [jiaiyan/element-plus-skills](https://github.com/jiaiyan/element-plus-skills/tree/1d126b39a80523665cc6290f70ce25aa89d708ae). Its component, layout and localization guides stay together so the relative references resolve.

Use the project-specific [sarasystem-element-plus](../../.ai/skills/sarasystem-element-plus/SKILL.md) skill for Element Plus work in the user panel. It records SaraSystem’s conventions and checks component APIs against the installed package and official documentation. Boost exposes it to Codex alongside the other project skills.
