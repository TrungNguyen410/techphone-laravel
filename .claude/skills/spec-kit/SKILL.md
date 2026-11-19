---
name: spec-kit
description: "GitHub Spec Kit — Spec-Driven Development (SDD). Use when the user wants to build a feature or a new project from a written spec instead of vibe-coding: define project principles (constitution), write a feature spec (what/why), clarify ambiguities, produce a technical plan, break it into tasks, analyze consistency, implement, and converge. Triggers: 'spec kit', 'speckit', '/speckit-*', 'spec-driven', 'write a spec first', 'constitution', 'plan then tasks then implement'."
user-invocable: true
---

# Spec Kit (Spec-Driven Development)

Vendored from https://github.com/github/spec-kit (MIT, commit in `UPSTREAM_COMMIT`).
Methodology background: `spec-driven.md`.

This skill bundles the official Spec Kit command prompts (`commands/`), document
templates (`templates/`) and helper scripts (`scripts/`). It routes the user's
request to the right step and runs that step's prompt.

## The workflow (run in order, one step per turn, user reviews each result)

| Step | Command file | Purpose | Output |
|---|---|---|---|
| 0 (once/project) | `commands/constitution.md` | Project principles: code quality, testing, UX, performance rules | `.specify/memory/constitution.md` |
| 1 | `commands/specify.md` | WHAT + WHY of a feature (no tech stack) | `specs/<NNN-feature>/spec.md` + new branch |
| 2 (optional, recommended) | `commands/clarify.md` | Up to 5 targeted questions, answers written back into spec | updated `spec.md` |
| 3 | `commands/plan.md` | HOW: tech stack, architecture, data model, contracts | `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md` |
| 3b (optional) | `commands/checklist.md` | "Unit tests for requirements" checklist (UX, security, API…) | `checklists/*.md` |
| 4 | `commands/tasks.md` | Dependency-ordered task list, `[P]` = parallelizable | `tasks.md` |
| 5 (optional, recommended) | `commands/analyze.md` | Read-only consistency check across spec/plan/tasks | report only |
| 6 | `commands/implement.md` | Execute tasks.md task by task | code |
| 7 | `commands/converge.md` | Compare code vs spec/plan, append missing work to tasks.md | updated `tasks.md` (then implement again) |

Routing: map the user's words to a step (`/speckit-plan …`, "speckit plan", "lập plan cho spec" → step 3).
If the user only says "spec kit" with a feature idea, start at step 1 (and step 0 first if no constitution exists).
Never skip ahead silently: if a prerequisite artifact is missing, say which step must run first.

## Bootstrap (first use in a project)

The command prompts expect a `.specify/` folder in the project root. If it does not exist, create it
by copying from THIS skill's directory (the folder containing this SKILL.md):

```
<skill>/templates/*.md          -> <project>/.specify/templates/
<skill>/scripts/powershell/*    -> <project>/.specify/scripts/powershell/   (Windows default)
<skill>/scripts/bash/*          -> <project>/.specify/scripts/bash/         (macOS/Linux)
<skill>/scripts/python/*        -> <project>/.specify/scripts/python/       (if neither shell fits)
<skill>/templates/constitution-template.md -> <project>/.specify/memory/constitution.md  (only if missing)
```

Pick ONE script flavor for the project: PowerShell on Windows, bash elsewhere.
Spec Kit uses git branches per feature (`001-feature-name`); if the project is not a git repo,
ask the user whether to `git init` or continue without branches.

Official alternative (does the bootstrap for you, needs Python 3.11+ and uv):
`uv tool install specify-cli` then `specify init . --integration claude` inside the project.

## Running a step

1. Read the step's file in `commands/` from this skill directory.
2. Resolve its placeholders before following it:
   - `$ARGUMENTS` / `{ARGS}` → the user's text after the command.
   - `{SCRIPT}` → the frontmatter `scripts:` entry for the chosen flavor (`ps:`, `sh:` or `py:`),
     with the leading `scripts/` rewritten to `.specify/scripts/`, run from the project root.
     Example: `scripts/powershell/setup-plan.ps1 -Json` → `.specify/scripts/powershell/setup-plan.ps1 -Json`.
   - `__SPECKIT_COMMAND_<NAME>__` → refer to that step as `/speckit-<name>` (e.g. `__SPECKIT_COMMAND_PLAN__` → `/speckit-plan`).
   - Paths like `templates/plan-template.md` → `.specify/templates/plan-template.md`.
3. `.specify/extensions.yml` hooks: if the file does not exist (normal for this vendored setup), skip the hook sections.
4. Follow the command prompt exactly, then stop and show the result; suggest the next step from the table.
