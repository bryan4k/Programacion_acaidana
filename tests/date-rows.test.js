'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { formatProgramDates } = require('../assets/date-rows.js');
const { rowCapacity, isWithinA4OverflowTolerance, availableVerseHeight } = require('../assets/a4-layout.js');
const { normalizeDecorationVisibility } = require('../assets/decor-visibility.js');
const { shouldShowDefaultArtwork, isCurrentImageRead, imageReplacementVisibility, footerDecorationVisibility } = require('../assets/image-replacement.js');

test('formats two program dates as separate non-empty lines', () => {
    assert.deepEqual(formatProgramDates('2026-10-01', '2026-10-08'), ['Jueves 01 Oct.', 'Jueves 08 Oct.']);
});

test('keeps legacy rows to one line when the optional second date is absent', () => {
    assert.deepEqual(formatProgramDates('2026-10-01', ''), ['Jueves 01 Oct.']);
});

test('uses the physical A4 edge as the only overflow boundary', () => {
    assert.equal(isWithinA4OverflowTolerance(1123, 1123), true);
    assert.equal(isWithinA4OverflowTolerance(1123, 1123 + 1), false);
    assert.equal(isWithinA4OverflowTolerance(1123, 1123 + 1, 'ushers'), false);
});

test('does not subtract decoration sizes or internal footer and verse reserves from A4 row capacity', () => {
    const design = { tableBodyFontSize: 17, logoSize: 104, headerHeight: 600, footerHeight: 600, verseHeight: 170 };
    assert.equal(rowCapacity(design, 'worship'), Math.floor(1123 / (17 * 2.7)));
    assert.equal(rowCapacity({ ...design, headerHeight: 90, footerHeight: 80 }, 'worship'), rowCapacity(design, 'worship'));
});

test('uses the full physical A4 height for ushers regardless of header and footer art sizes', () => {
    const baseDesign = { tableBodyFontSize: 17, logoSize: 104, headerHeight: 80, footerHeight: 90, verseHeight: 170 };
    const largeDecorations = { ...baseDesign, headerHeight: 600, footerHeight: 600, logoSize: 300 };
    assert.equal(rowCapacity(baseDesign, 'ushers'), Math.floor(1123 / (17 * 2.4)));
    assert.equal(rowCapacity(largeDecorations, 'ushers'), rowCapacity(baseDesign, 'ushers'));
});

test('measured page overflow still fails at the physical A4 edge', () => {
    assert.equal(isWithinA4OverflowTolerance(1123, 1123), true);
    assert.equal(isWithinA4OverflowTolerance(1123, 1124), false);
    const app = fs.readFileSync(require.resolve('../assets/app.js'), 'utf8');
    assert.match(app, /function allPagesFit\(\)[\s\S]*?isWithinA4OverflowTolerance\(page\.clientHeight, page\.scrollHeight/);
});

test('defaults legacy decorations to visible and persists independent hide settings without a table toggle', () => {
    assert.deepEqual(normalizeDecorationVisibility(), {
        logo: true, headerLeft: true, headerRight: true, headerCenter: true,
        watermark: true, footer: true, verse: true,
    });
    assert.deepEqual(normalizeDecorationVisibility({ logo: false, footer: false }), {
        logo: false, headerLeft: true, headerRight: true, headerCenter: true,
        watermark: true, footer: false, verse: true,
    });
    assert.equal(Object.hasOwn(normalizeDecorationVisibility(), 'programTable'), false);
});

test('keeps a custom replacement visible while hiding its fallback artwork', () => {
    assert.deepEqual(imageReplacementVisibility(true, true), {
        customImageHidden: false,
        defaultArtworkHidden: true,
    });
    assert.deepEqual(imageReplacementVisibility(true, false), {
        customImageHidden: true,
        defaultArtworkHidden: false,
    });
    assert.deepEqual(imageReplacementVisibility(false, true), {
        customImageHidden: true,
        defaultArtworkHidden: true,
    });
});

test('footer decoration visibility never hides coordinator content and replaces default artwork', () => {
    assert.deepEqual(footerDecorationVisibility(true, true), {
        customImageHidden: false,
        defaultArtworkHidden: true,
        footerContentHidden: false,
    });
    assert.deepEqual(footerDecorationVisibility(true, false), {
        customImageHidden: true,
        defaultArtworkHidden: false,
        footerContentHidden: false,
    });
    assert.deepEqual(footerDecorationVisibility(false, true), {
        customImageHidden: true,
        defaultArtworkHidden: true,
        footerContentHidden: false,
    });
    assert.deepEqual(footerDecorationVisibility(false, false), {
        customImageHidden: true,
        defaultArtworkHidden: true,
        footerContentHidden: false,
    });
});

test('ignores image reads that complete after a newer selection', () => {
    assert.equal(isCurrentImageRead(1, 2), false);
    assert.equal(isCurrentImageRead(2, 2), true);
});


test('bounds the verse by actual coordinator content, not decorative footer height or sheet padding', () => {
    assert.equal(availableVerseHeight(1123, 650, 90, 18), 365);
    assert.equal(availableVerseHeight(1123, 1020, 90, 18), 0);
    const app = fs.readFileSync(require.resolve('../assets/app.js'), 'utf8');
    assert.doesNotMatch(app, /Number\(state\.design\.footerHeight\)\s*\|\|\s*0,\s*margin,/);
});

test('does not inject decorative footer height as program-sheet padding and retains the physical edge guard', () => {
    const app = fs.readFileSync(require.resolve('../assets/app.js'), 'utf8');
    assert.doesNotMatch(app, /\.program-sheet\s*\{\s*padding-bottom:\s*\$\{numeric\('footerHeight'\)\}px;/);
    assert.match(app, /isWithinA4OverflowTolerance\(page\.clientHeight, page\.scrollHeight/);
});

test('keeps decorative footer imagery behind the sheet content in the shared export stylesheet', () => {
    const css = fs.readFileSync(require.resolve('../assets/styles.css'), 'utf8');
    assert.match(css, /\.custom-footer-image\s*\{[^}]*z-index:\s*-1;/);
    assert.match(css, /\.landscape-footer\s*\{[^}]*z-index:\s*-1;/);
    assert.match(css, /\.program-sheet\s*\{[^}]*isolation:\s*isolate;/);
});

test('layers the usher logo behind heading text without resizing it', () => {
    const css = fs.readFileSync(require.resolve('../assets/styles.css'), 'utf8');
    const app = fs.readFileSync(require.resolve('../assets/app.js'), 'utf8');
    assert.match(css, /\.ushers-sheet\s+\.logo-wrap\s*\{[^}]*z-index:\s*-1;[^}]*width:\s*145px;/);
    assert.match(app, /#churchLogo \{ max-width: \$\{numeric\('logoSize'\)\}px;/);
});
