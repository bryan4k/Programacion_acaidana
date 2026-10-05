# Footer Toggle and Image Replacement

## Objective
Make footer decoration visibility independent from the footer text area and ensure a custom footer image replaces the default landscape-footer artwork.

## Problem and Why
The footer decoration toggle currently hides `.sheet-footer`, removing coordinator labels and names. The decoration visibility pass also reverses the default artwork hiding set by `updateDesign()` when a custom footer image is present.

## Scope
- Keep footer visibility limited to custom/default footer artwork; never hide `.sheet-footer`.
- Apply the shared image-replacement visibility contract to custom and default footer artwork, based on decoration visibility and custom-image presence.
- Add pure-helper regression coverage and wire it into application logic.
- Do not disturb table, verse, or A4 behavior.

## Constraints
- Local change only on the current feature branch. Do not push, access remotes, update a PR, modify `.atl/`, or start native review.
- Strict TDD enabled; source: session instruction; runner: Node built-in `node:test`.
- No dependencies.
- Route: delegated direct, one scoped implementation task.
- Forecast: under 400 authored changed lines.
- Runtime smoke: only if a local preview is already safely available.

## Acceptance Criteria
- [x] Footer decoration toggle never hides `.sheet-footer` or coordinator labels/names.
- [x] Custom footer image shows and default landscape-footer artwork hides when uploaded.
- [x] Removing the custom image restores default artwork.
- [x] Visibility off/on applies to footer decoration only.
- [x] Pure helper contract is tested and used by application logic; table, verse, and A4 logic remain unchanged.

## Checks
- `node --test tests/date-rows.test.js`
- `php tests/date-template-validation.php`
- `php -l app/bootstrap.php`
- `php -l index.php`
- `node --check assets/app.js`
- `git diff --check`
- Browser smoke only if an already-running local preview is safely available.

## Progress
- [x] FTR-1: Test the footer visibility/replacement contract first, observe RED, implement the helper usage and footer-only visibility update, then verify and commit.
  - Route: delegated.
  - Rollback boundary: remove the FTR-1 commit to restore prior footer visibility behavior and tests.
  - RED: `node --test tests/date-rows.test.js` — failed as intended, 11 passed / 1 failed because `footerDecorationVisibility` did not yet exist.
  - GREEN: `node --test tests/date-rows.test.js` — 12 passed, 0 failed.
  - Checks: `php tests/date-template-validation.php` — passed; `php -l app/bootstrap.php` and `php -l index.php` — no syntax errors; `node --check assets/app.js` — passed; `git diff --check` — passed.
  - Runtime: Browser smoke not run; no already-running local preview was safely available/established.
  - Implementation: footer visibility now uses a pure helper that independently hides/shows custom or default artwork, while `.sheet-footer` is excluded from decoration toggling; `updateDesign()` no longer overrides default footer artwork visibility.
  - Commit: `e6b95b2` (`fix(footer): isolate decoration visibility from coordinator content`).

## Next Step
FTR-1 is implemented, verified, and committed locally. No remote action or native review was performed.

## Relevant Files
- `assets/app.js` — design rendering and decoration visibility.
- `assets/image-replacement.js` — pure image replacement contract.
- `tests/date-rows.test.js` — built-in Node regression coverage.
