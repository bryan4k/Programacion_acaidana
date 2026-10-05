# A4 Page Bounds and Removable Decorations

## Objective
Validate content against the physical A4 sheet boundary rather than internal margins, and allow every non-table decorative element to be hidden and restored independently.

## Problem and Why
The original row-capacity estimate subtracted reserved internal space for decorations and header lead-in, so it could block a layout even while all information remained inside the physical sheet. Decorative sizing must not change estimated row capacity; the browser's measured physical A4 edge remains the hard content boundary. Uploaded images can be removed, but built-in decorations reappear or remain visible, and cannot be hidden as elements.

## Scope
- Preserve physical A4-bound checks for print and Word-export flows; saving content beyond a page estimate or physical output boundary remains allowed.
- Preserve fixed A4 dimensions and never allow the main program table to be hidden or deleted.
- Add independent, persistent hide/restore controls for the built-in decorative elements: logo/emblem, header ornaments/backgrounds, watermark, footer, and verse frame/card.
- Keep older saved templates compatible, with decorations visible by default when no visibility settings exist.
- Add regression tests for page-edge versus internal-margin overflow and for visibility persistence/validation.

## Constraints
- Work locally on the current `fix/a4-overflow-tolerance` branch; do not push or update PR #2 without fresh explicit authorization for this new scope.
- Strict TDD is enabled: add failing regression tests first, observe RED, implement to GREEN, then refactor.
- Use built-in Node and PHP test runners only; no new dependencies.
- Preserve unrelated `.atl/` files.
- Route: delegated direct. Read-only mapping was delegated because behavior spans 4+ files; one bounded writer will own implementation and tests.
- Forecast: under 400 authored changed lines for this follow-up; `ask-on-risk` delivery strategy.

## Acceptance Criteria
- [x] Decorative sizing or internal margins do not block saving when all information remains within the outer A4 bounds.
- [x] Information crossing the physical A4 boundary continues to block print/Word export; printed sheet remains 210 × 297 mm.
- [x] Each named non-table decoration can be hidden and restored independently; its visibility persists after save/reload.
- [x] The main program table cannot be hidden or deleted.
- [x] Legacy templates without visibility settings still render all decorations by default.
- [x] Regression tests demonstrate RED before implementation and GREEN after; PHP syntax and diff checks pass.

## Checks
- `node --test tests/date-rows.test.js`
- `php tests/date-template-validation.php`
- `php -l app/bootstrap.php`
- `php -l index.php`
- `git diff --check`
- Local browser smoke: move content into the former margin reserve while keeping it inside the A4 boundary; verify save/print/export guards accept it. Move content past the paper edge and verify blocking. Hide each non-table decoration, save/reload, and restore it; verify the table remains present.

## Progress
- [x] Mapped the current checks and design controls across client, PHP, and CSS. Found that custom uploaded assets already have remove buttons; the missing behavior is independently hiding built-in/default decorations.
- [x] A4-BND-1: Replace internal-margin/decoration overflow checks with physical A4-edge checks; keep save, print, Word export, and server validation consistent. Added regressions first (Node RED: 2 failures; PHP RED: rejected 17 rows), then removed worship footer/verse/logo reserves and 10 mm tolerance from client/server capacity checks. Client measured pages reject any scroll-height beyond the fixed sheet height. GREEN: Node 5/5, PHP validation passed, PHP syntax passed, diff check passed. Commit `a3c3511`; RDD assessment medium, `review_due=false` (`under_budget`).
- [x] A4-DEC-1: Added independently persistent visibility switches for logo, left/right/center header layers, watermark, footer, and verse card; old templates default visible. Controls are reversible and accessible by changing action label; no table control exists. RED: Node test initially failed because the visibility helper did not exist; PHP legacy visibility assertion failed. GREEN: Node 6/6 and PHP validation passed, with PHP syntax, JS syntax, and diff checks passing. Browser smoke: all seven controls hid/restored; saved a hidden-logo local template, reloaded it, and verified the restore action persisted while the table remained present. Commit `9065fab`; RDD assessment medium, `review_due=false` (`under_budget`).
- [x] A4-BND-2: Followed the clarified rule that decorative logo size cannot lower usher row capacity. RED: Node failed (large logo produced 19 rows vs. 21), and PHP rejected a 21-row usher page with a 180px logo. Removed logo-size reserve from client/server usher estimates while retaining the fixed header reserve. GREEN: Node 6/6, PHP validation, PHP syntax, JS syntax, and diff checks passed; 22 rows remain blocked by the estimated A4 edge. Commit `c8d1c86`; RDD assessment medium, `review_due=false` (`under_budget`).
- [x] A4-BND-3: Decoupled decorative header/footer artwork dimensions from sheet content room. Removed footer-image height from `.program-sheet` bottom padding and verse-height availability; actual coordinator content height remains accounted for, and the fixed real-content lead-in / hard physical A4 overflow guard remain unchanged. RED: Node 12 passed, 1 failed on injected padding. GREEN: Node 13/13, PHP validation passed, PHP syntax checks passed, JS syntax checks passed, diff check passed. Browser smoke not run (no safe running local app confirmed). Behavior+tests commit `8fe2ca7`; RDD review remains parent-owned.
- [x] A4-BND-4: Removed the remaining fixed 300px worship and 265px usher lead-in reserves from client and PHP row-capacity estimates; estimates now use the full 1123px A4 height with existing row-height formulas unchanged. RED: Node 12/14 passed, 2 failed on worship/usher capacities (17 vs 24; 21 vs 27); PHP validation rejected the 24-row worship case under the old fixed reserve. GREEN: Node 14/14, PHP validation passed, PHP syntax checks passed, JS syntax checks passed, and diff check passed. The measured client physical edge guard remains and regression verifies `allPagesFit()` uses `scrollHeight <= clientHeight` via the A4 helper. Browser smoke not run (no safe running local app confirmed). Commit `3a233ab`; no remote or review actions taken.
- [x] A4-BND-5: Removed row-capacity blocking from add-row controls, `saveTemplate()`, and PHP template validation (including multipage documents); removed the inline page-fit warning/state. Users can edit and save content beyond the A4 estimate, subject to the existing 300-row input safety bound. Print and Word export still call `allPagesFit()` and retain the physical fit guard; fixed A4 dimensions and the verse-height boundary are unchanged. RED: Node test failed because saving still called `allPagesFit()`; PHP rejected rows 25/28 and multipage 25 against the fit estimate. GREEN: Node 15/15 and PHP validation passed, both PHP syntax checks passed, JS syntax check passed, and diff check passed. Browser smoke not run (no safe local preview confirmed). Commit `8e12de9`.
- [x] A4-BND-6: Removed only the A4 fit guard from the print/PDF click handler; it now always builds print pages and calls `window.print()`. Fixed A4 output dimensions and the Word export `allPagesFit()` guard were unchanged. RED: Node 14/15 passed, 1 failed because print still called `allPagesFit()` and alerted. GREEN: Node 15/15; PHP validation, PHP syntax checks, JS syntax check, and diff check passed. Browser smoke not run (no safe local preview confirmed). Behavior/test commit `cb25398`.
- [x] A4-BND-7: Removed preview `page-overflow` scroll-height detection and the obsolete red-border CSS rule; there are no remaining runtime uses of the class. Preserved the verse-height `clientHeight` calculation, fixed A4 dimensions, print behavior, row/verse controls, and Word export guard. RED: Node 15/16 passed, 1 failed because `updateVerseHeightLimit()` still toggled the state. GREEN: Node 16/16, PHP validation, both PHP syntax checks, JS syntax check, and `git diff --check` passed. Browser smoke not run (no safe local preview confirmed). Behavior/test commit `1dfdd3f`.
- [ ] A4-BND-8: Investigate and reproduce blank print/PDF output, identify the actual cause before implementing a fix, then add a regression test that fails for that cause. Preserve fixed A4 output dimensions and the current no-fit-block print behavior; do not make unrelated changes. Strict TDD: observe RED, implement, then GREEN. Verify with the listed Node/PHP runners and syntax/diff checks; report whether browser smoke was possible. Route: delegated direct, single bounded writer. Forecast: under 400 authored changed lines; local-only, no push/PR.
  - Investigation: confirmed `#printPages` is a direct sibling of `.preview-sheet-stage`, not its descendant, so the print rule hiding the preview stage does not hide the print pages. The running local app opened, but triggering print timed out in the browser UI bridge and no print preview was observed. No cause-specific test or source change is justified yet; task remains pending reproduction.

## Next Step
A4-BND-8 remains pending: obtain a reproducible blank-output case or environment-specific evidence before selecting a fix. Parent to review committed work after completion. Do not push or update the open PR. The browser smoke left a temporary `A4 decoration smoke` template in local development browser storage; permanent deletion confirmation was not accepted, so request user authorization before cleanup.

## Relevant Files
- `assets/a4-layout.js` — client row-capacity and physical A4-bound helpers.
- `assets/app.js` — editor state, render, save, print, export, and asset controls.
- `assets/styles.css` — preview and print sheet dimensions/overflow.
- `app/bootstrap.php` — persisted template normalization and validation.
- `index.php` — asset controls and script loading.
- `tests/date-rows.test.js`, `tests/date-template-validation.php` — existing regression suites.
