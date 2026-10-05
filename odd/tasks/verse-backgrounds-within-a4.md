# Verse backgrounds within A4 bounds

## Objective
Keep all decorative images behind text and allow verse content to grow naturally without exceeding the physical A4 sheet.

## Problem and why
Decorative imagery can obscure footer text, while the verse-height control/schema cap of 360px truncates available capacity even when the A4 sheet has room. The user authorized local implementation of the clarified behavior.

## Scope and constraints
- Ensure decorative images remain behind text in preview, print, and Word export.
- Let the verse box grow up to the remaining A4 space and no further; keep the sheet's physical edge as the hard bound.
- Preserve print/export parity and existing layout/table stacking.
- Local only; no remotes, PRs, or edits to `.atl/`.
- No new dependencies.

## Authorized scope
Current branch local source/tests and this feature document; Engram mirror at `odd/verse-backgrounds-within-a4/tasks`.

## Task checklist
- [x] VAB-1: Keep decorative images behind text and dynamically bound verse growth to available A4 space.

## Acceptance criteria
- Decorative custom and built-in images cannot paint above text in preview, print clones, and Word export.
- Verse content grows naturally with no arbitrary 360px schema/control limit; the control respects current remaining A4 capacity.
- No output path silently clips overflowing verse content; sheet remains fixed A4.
- Focused tests verify layering/bounds and existing behavior.

## Checks
- Strict TDD: enabled (source: current task/runtime instruction); runner: Node built-in test runner and PHP built-in CLI tests.
- RED observed: the new A4 helper test failed because `availableVerseHeight` did not exist; PHP rejected verseHeight 900 under the former 360px cap.
- GREEN/refactor: all focused tests pass; schema, control range, dynamic available-height clamp, preview overflow warning, and shared stylesheet layering implemented.
- `node --test tests/date-rows.test.js` — PASS (10 tests).
- `php tests/date-template-validation.php` — PASS.
- `php -l app/bootstrap.php` — PASS.
- `php -l index.php` — PASS.
- `node --check assets/app.js` — PASS.
- `node --check assets/a4-layout.js` — PASS.
- `git diff --check` — PASS.
- Browser smoke — NOT RUN; no already-running local preview was available.

## Forecast and route
- Forecast: one bounded behavior task; expected under 400 authored changed lines.
- Route: delegated direct (assigned writer); strict TDD.
- Work-unit commit: `b5e45a61e3ac8c4102c15db2421968b68a37fcfd` (`fix(layout): bound verse and keep imagery behind text`).

## Progress and next step
- VAB-1 complete and verified. Code and tests are committed; this document and its Engram mirror record the evidence. No further implementation remains.
