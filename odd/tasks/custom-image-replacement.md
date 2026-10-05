# Custom Image Replacement

## Objective
Ensure uploaded custom images visibly replace built-in/default test artwork in the design preview, including on rapid consecutive selections.

## Problem and Why
Custom header and watermark images can coexist visually with built-in ornament/motif layers, making replacement appear incomplete. Fast FileReader callbacks may also complete out of order and restore an older image selection.

## Scope
- Suppress the default heading ornament when a custom header image is selected.
- Suppress default header-side motifs when a custom watermark image is selected, if verified by regression.
- Prevent stale FileReader completion from replacing a more recent selection where practical.
- Add focused regression coverage.

## Constraints
- Work locally on the current feature branch; do not push, create/update a PR, access remotes, or modify `.atl/`.
- Strict TDD is enabled: add tests and observe RED before implementation, then GREEN and refactor.
- Use built-in Node/PHP test runners only; no dependencies.
- Route: delegated direct; task was assigned as a scoped implementation unit.
- TDD mode: enabled; source: session instruction; runner: Node built-in test runner.
- Forecast: under 400 authored changed lines; delivery strategy: ask-on-risk.

## Acceptance Criteria
- [x] Custom header and watermark images replace their respective default artwork rather than layering with it.
- [x] A stale earlier FileReader completion cannot restore an outdated image after a newer selection.
- [x] Regression tests demonstrate RED before implementation and GREEN after; required syntax and diff checks pass.

## Checks
- `node --test tests/date-rows.test.js`
- `php tests/date-template-validation.php`
- `php -l app/bootstrap.php`
- `php -l index.php`
- JavaScript syntax check
- `git diff --check`
- Runtime: browser smoke if feasible; otherwise document why unavailable.

## Progress
- [x] IMG-1: Add regression tests, fix default artwork suppression and stale upload callbacks, verify, and commit this work unit.
  - Route: delegated.
  - RED: `node --test tests/date-rows.test.js` failed because `assets/image-replacement.js` did not yet exist (module-not-found).
  - GREEN: `node --test tests/date-rows.test.js` — 8 passed, 0 failed.
  - Checks: `php tests/date-template-validation.php` — passed; `php -l app/bootstrap.php` and `php -l index.php` — no syntax errors; `node --check assets/app.js` and `node --check assets/image-replacement.js` — passed; `git diff --check` — passed.
  - Runtime: Browser smoke not run; no local preview/browser session was established.
  - Implementation note: the decoration visibility pass was overriding the earlier fallback hidden state. Replacement visibility is now applied separately to custom images and default artwork; default ornaments/motifs hide while the selected custom image remains visible. Per-input read versions prevent stale logo/design FileReader callbacks from applying.
  - Parent spot-check correction: the first implementation incorrectly hid both the fallback and custom image in a shared selector group. Added regression assertions for custom-image visibility, fallback suppression, empty selection, and decoration toggle-off; corrected the two-element visibility handling.
  - Correction RED: `node --test tests/date-rows.test.js` failed because `imageReplacementVisibility` was not implemented.
  - Correction GREEN: `node --test tests/date-rows.test.js` — 8 passed, 0 failed.
  - Correction checks: `php tests/date-template-validation.php` — passed; PHP lint for `app/bootstrap.php` and `index.php`, JS syntax checks, and `git diff --check` — passed.
  - Commit: updated work-unit commit identity is reported in the handoff; embedding its own hash would change that hash.

## Next Step
The work unit is implemented, checked, and committed locally. Do not push; delivery needs fresh authorization.

## Relevant Files
- `assets/app.js` — design rendering and file upload readers.
- `index.php` — design preview markup and default artwork.
- `tests/date-rows.test.js` — built-in Node regression coverage.
