# Two Dates per Program Row

## Objective
Allow one worship-program row to contain two independent dates and display them vertically in the date cell.

## Problem and Why
The program table currently stores and displays only one date per row. The user needs a second date in that same cell, directly below the first, and confirmed that a second date picker is the intended input.

## Scope
- Add an optional second date picker to worship-program rows.
- Persist, restore, validate, and render both dates, with the second date below the first in preview, print, and Word export.
- Keep existing saved rows without a second date compatible; keep usher rows unchanged.
- Add dependency-free tests before implementation using Node's built-in test runner and PHP CLI.

## Constraints
- Work only on `feat/two-dates-per-program-row`; do not push.
- Strict TDD is enabled by the session instructions. Add failing tests first, observe RED, implement to GREEN, then refactor and rerun.
- Node v24.21.0 and PHP 8.5.10 are available; there is no existing test harness or dependency manifest.
- Preserve unrelated untracked `.atl/` files.
- Route: delegated direct implementation; mapping trigger fired because the date flow crosses the UI, persisted template validation, and styling. The bounded writer will own the tests and implementation together.
- Forecast: under 400 authored changed lines; delivery strategy `ask-on-risk`.

## Acceptance Criteria
- [x] DATE-1: Program rows now carry optional `secondDate`; row normalization/serialization preserve it and legacy rows remain valid. Browser save/reload/reselect confirmed the second date persisted.
- [x] DATE-1: Both dates render as block-level lines through the shared row renderer used by preview, print, and Word export; an empty second date creates no line. Browser preview smoke confirmed the stacked appearance.
- [x] DATE-1: PHP validation accepts a valid optional second worship date, rejects invalid dates, and leaves ushers on their existing date-only schema.
- [x] DATE-1: Node and PHP tests observed RED before source implementation and GREEN after implementation; required syntax and diff checks passed.

## Checks
- `node --test tests/date-rows.test.js`
- `php tests/date-template-validation.php`
- `php -l app/bootstrap.php`
- `php -l index.php`
- `git diff --check`
- Browser smoke check: set both dates on one worship row, save/reload/reselect in local mode, and inspect the stacked preview. Print-dialog and Word-download smoke remain unverified.

## Progress
- [x] Explored the date-cell flow and identified client rendering, CSS, and server validation as affected areas.
- [x] Confirmed no existing test files or runner; user authorized adding a minimal runner.
- [x] DATE-1 implementation and checks. RED: Node failed because the shared date formatter did not exist; PHP failed because `secondDate` was dropped. GREEN: both test commands passed after implementation; browser preview displayed two stacked dates.
- [x] Committed implementation as `7842cadd199b35fd602e587f0acabda50b6efdd1` (`feat: support two dates per program row`).
- [x] Parent spot check: `node --test tests/date-rows.test.js` passed (2/2).
- [x] Risk assessment: medium; review was not due (`under_budget`). User declined candidate-specific review; RDD remains enabled for future candidates.
- [x] Browser smoke confirmed save, reload, and reselect retain the second date and render both dates stacked.

## Next Step
Implementation and checks complete. Browser smoke confirmed the second date appears stacked and persists after save/reload/reselect. Print-dialog and Word-download smoke remain unverified. The temporary browser test template `QA temporal - dos fechas 2026-10-05` could not be removed after the browser confirmation interaction timed out; delete it from the template list if it is not wanted.
