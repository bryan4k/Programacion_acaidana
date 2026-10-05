# A4 Page Bounds and Removable Decorations

## Objective
Validate content against the physical A4 sheet boundary rather than internal margins, and allow every non-table decorative element to be hidden and restored independently.

## Problem and Why
The current row-capacity estimate subtracts reserved internal space for the footer, verse box, and logo, so it can block a layout even while all information remains inside the physical sheet. Browser overflow measurement also treats the padded content box as the limit. Uploaded images can be removed, but built-in decorations reappear or remain visible, and cannot be hidden as elements.

## Scope
- Replace margin/decoration-based overflow blocking with a physical A4-bound check shared by save, print, and Word-export flows; keep blocking when information crosses the outer sheet edge.
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
- [ ] Decorative sizing or internal margins do not block saving when all information remains within the outer A4 bounds.
- [ ] Information crossing the physical A4 boundary continues to block save/export/print; printed sheet remains 210 × 297 mm.
- [ ] Each named non-table decoration can be hidden and restored independently; its visibility persists after save/reload.
- [ ] The main program table cannot be hidden or deleted.
- [ ] Legacy templates without visibility settings still render all decorations by default.
- [ ] Regression tests demonstrate RED before implementation and GREEN after; PHP syntax and diff checks pass.

## Checks
- `node --test tests/date-rows.test.js`
- `php tests/date-template-validation.php`
- `php -l app/bootstrap.php`
- `php -l index.php`
- `git diff --check`
- Local browser smoke: move content into the former margin reserve while keeping it inside the A4 boundary; verify save/print/export guards accept it. Move content past the paper edge and verify blocking. Hide each non-table decoration, save/reload, and restore it; verify the table remains present.

## Progress
- [x] Mapped the current checks and design controls across client, PHP, and CSS. Found that custom uploaded assets already have remove buttons; the missing behavior is independently hiding built-in/default decorations.
- [x] A4-BND-1: Replace internal-margin/decoration overflow checks with physical A4-edge checks; keep save, print, Word export, and server validation consistent. Added regressions first (Node RED: 2 failures; PHP RED: rejected 17 rows), then removed worship footer/verse/logo reserves and 10 mm tolerance from client/server capacity checks. Client measured pages now reject any scroll-height beyond the fixed sheet height; usher capacity remains unchanged. GREEN: Node 5/5, PHP validation passed, PHP syntax passed, diff check passed. Browser smoke unavailable in this terminal-only session. Commit `a3c3511`; RDD assessment medium, `review_due=false` (`under_budget`).
- [x] A4-DEC-1: Added independently persistent visibility switches for logo, left/right/center header layers, watermark, footer, and verse card; old templates default visible. Controls are reversible and accessible by changing action label; no table control exists. RED: Node test initially failed because the visibility helper did not exist; PHP legacy visibility assertion failed. GREEN: Node 6/6 and PHP validation passed, with PHP syntax, JS syntax, and diff checks passing. Browser smoke unavailable in this terminal-only session. Commit `9065fab`; RDD assessment medium, `review_due=false` (`under_budget`).

## Next Step
Parent to review committed work and determine whether an authorized browser smoke can be performed. Keep all work local until the user authorizes updating the open PR.

## Relevant Files
- `assets/a4-layout.js` — client row-capacity and physical A4-bound helpers.
- `assets/app.js` — editor state, render, save, print, export, and asset controls.
- `assets/styles.css` — preview and print sheet dimensions/overflow.
- `app/bootstrap.php` — persisted template normalization and validation.
- `index.php` — asset controls and script loading.
- `tests/date-rows.test.js`, `tests/date-template-validation.php` — existing regression suites.
