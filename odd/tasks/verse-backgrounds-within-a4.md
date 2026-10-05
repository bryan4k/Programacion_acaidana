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
- Decorative custom and built-in images cannot paint above text in preview, print clones, and Word export, including the default footer wrapper stacking context.
- Usher logos paint behind heading text where their absolute header slot overlaps, without changing the prior 145px slot or user-selected logo sizing.
- Verse content grows naturally with no arbitrary 360px schema/control limit; the control respects current remaining A4 capacity.
- No output path silently clips overflowing verse content; sheet remains fixed A4.
- Focused tests verify layering/bounds and existing behavior.

## Checks
- Strict TDD: enabled (source: current task/runtime instruction); runner: Node built-in test runner and PHP built-in CLI tests.
- RED observed: the new A4 helper test failed because `availableVerseHeight` did not exist; PHP rejected verseHeight 900 under the former 360px cap.
- GREEN/refactor: all focused tests pass; schema, control range, dynamic available-height clamp, preview overflow warning, shared stylesheet layering, and usher logo text layering implemented.
- Follow-up RED: new regression tests failed while `.landscape-footer` had `z-index: 0` and usher logo column remained 145px; both passed after correction.
- Logo-sizing correction RED: regression failed because the logo wrapper lacked negative stacking and the earlier correction had reduced its width; layering is now negative within sheet isolation and original 145px width/user sizing restored.
- `node --test tests/date-rows.test.js` — PASS (11 tests).
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
- Layering correction commit: `90a4562d7b4f405a2657835a9d7532677d193cfd` (`fix(layout): lower footer stack behind sheet text`).
- Logo-layering correction commit: `ab907cc932cc1638ac7ce6f3766375fca1832a13` (`fix(layout): layer usher logo behind heading text`).

## Progress and next step
- VAB-1 and the footer/logo layering corrections are complete and verified. Logo dimensions and selected sizing are preserved; this document and its Engram mirror record the evidence. No further implementation remains.
