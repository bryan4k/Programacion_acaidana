'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { formatProgramDates } = require('../assets/date-rows.js');
const { rowCapacity, isWithinA4OverflowTolerance, availableVerseHeight } = require('../assets/a4-layout.js');
const { normalizeDecorationVisibility } = require('../assets/decor-visibility.js');
const { shouldShowDefaultArtwork, isCurrentImageRead, imageReplacementVisibility } = require('../assets/image-replacement.js');

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
    const design = { tableBodyFontSize: 17, logoSize: 104, footerHeight: 310, verseHeight: 170 };
    assert.equal(rowCapacity(design, 'worship'), 17);
});

test('does not reduce usher row capacity for a large decorative logo', () => {
    const baseDesign = { tableBodyFontSize: 17, logoSize: 104, footerHeight: 150, verseHeight: 170 };
    const largeLogoDesign = { ...baseDesign, logoSize: 180 };
    assert.equal(rowCapacity(baseDesign, 'ushers'), 21);
    assert.equal(rowCapacity(largeLogoDesign, 'ushers'), 21);
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

test('ignores image reads that complete after a newer selection', () => {
    assert.equal(isCurrentImageRead(1, 2), false);
    assert.equal(isCurrentImageRead(2, 2), true);
});


test('bounds the verse height by the remaining physical A4 space after the footer', () => {
    assert.equal(availableVerseHeight(1123, 650, 90, 150, 18), 215);
    assert.equal(availableVerseHeight(1123, 900, 90, 150, 18), 0);
});

test('keeps decorative footer imagery behind the sheet content in the shared export stylesheet', () => {
    const css = fs.readFileSync(require.resolve('../assets/styles.css'), 'utf8');
    assert.match(css, /\.custom-footer-image\s*\{[^}]*z-index:\s*-1;/);
    assert.match(css, /\.landscape-footer\s*\{[^}]*z-index:\s*-1;/);
    assert.match(css, /\.program-sheet\s*\{[^}]*isolation:\s*isolate;/);
});

test('keeps the usher logo within its reserved column so it cannot overlap heading text', () => {
    const css = fs.readFileSync(require.resolve('../assets/styles.css'), 'utf8');
    assert.match(css, /\.ushers-sheet\s+\.logo-wrap\s*\{[^}]*width:\s*90px;/);
});
