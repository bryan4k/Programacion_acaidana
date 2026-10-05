# A4 Small Overflow Tolerance

## Objective
Allow saving a template when decorative sizing causes a small A4 layout overflow, while continuing to prevent meaningful content clipping.

## Problem and Why
Increasing the footer image height reduces row capacity in both the browser and PHP template validator. The browser also rejects any measured overflow, even when it is within a small visual tolerance. The user wants more leeway than 5 mm; use 10 mm as the initial tolerance.

## Scope
- Align client and PHP validation so small overflow up to 10 mm does not block saving.
- Keep meaningful content overflow blocked; do not change the fixed A4 print dimensions.
- Add regression coverage for accepted small overflow and rejected larger overflow.

## Constraints
- Work locally on `fix/a4-overflow-tolerance`; do not push or create/update a PR without fresh explicit authorization.
- Strict TDD is enabled: write regression tests first, observe RED, then implement to GREEN and refactor.
- Use built-in Node and PHP test runners only; no new dependencies.
- Preserve unrelated `.atl/` files.
- Route: delegated direct; client and PHP validation plus tests/docs involve multiple non-trivial files. Mapping was delegated because behavior spans 4+ files.
- Forecast: under 400 authored changed lines; `ask-on-risk` delivery strategy.

## Acceptance Criteria
- [x] A template with overflow at or below 10 mm saves successfully in client and server validation.
- [x] A template exceeding the tolerance by a meaningful amount is still rejected before save, preserving protection from clipping rows/text.
- [x] Regression tests demonstrate RED before implementation and GREEN after; syntax and diff checks pass.
- [x] Fixed A4 page dimensions remain unchanged.

## Checks
- `node --test tests/date-rows.test.js`
- `php tests/date-template-validation.php`
- `php -l app/bootstrap.php`
- `php -l index.php`
- `git diff --check`
- Local browser smoke: increase footer height and verify a mildly overflowing template can be saved while larger content overflow remains blocked if reproducible.

## Progress
- [x] Mapped the A4 overflow checks: browser `pageCapacity()`/`allPagesFit()` and server `validate_template()` both couple decorative height with content row capacity; print remains fixed A4 with hidden overflow.
- [x] A4-1: Add regression tests, implement and verify the 10 mm tolerance consistently on the client and server, and commit the work unit.
  - RED: `node --test tests/date-rows.test.js` failed because the tolerance helper did not exist; `php tests/date-template-validation.php` failed because a 5-row page was accepted.
  - GREEN: `node --test tests/date-rows.test.js` — 5 passed, 0 failed; `php tests/date-template-validation.php` — passed.
  - Checks: `php -l app/bootstrap.php` — no syntax errors; `php -l index.php` — no syntax errors; `git diff --check` — passed.
  - Runtime: Browser smoke not run; no safe local preview was established. Fixed print A4 dimensions were not changed.
  - Scope spot-check: usher regressions failed before correction (Node expected capacity 20 but observed 21; PHP accepted 21 rows). After capacity correction, an additional test for measured overflow also failed for ushers; both now pass with worship-only tolerance.
  - Implementation note: tolerance is 37.8 CSS px (10 mm at 96 px/in) and applies only to worship row capacity and measured page overflow; usher row capacity remains unchanged. Server validation preserves this distinction for both versioned and legacy payloads.
  - Commit: final work-unit commit `fix(layout): allow 10mm A4 overflow tolerance` (its identity is reported in the handoff; embedding a commit's own hash in its tree would change that hash).

## Next Step
The work unit is implemented and checked locally. Do not push; delivery needs fresh authorization.

## Relevant Files
- `assets/app.js` — client row capacity, save/print validation, and overflow measurement.
- `app/bootstrap.php` — server-side template validation.
- `tests/date-template-validation.php` — PHP validation coverage.
- `tests/date-rows.test.js` — existing built-in Node regression suite.
