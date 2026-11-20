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
The five project skills and their supporting files share the path used by Codex and Boost. The existing `AGENTS.md` symlink still points to `CLAUDE.md`, so both instruction paths share the same file.
