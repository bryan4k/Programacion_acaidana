'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { formatProgramDates } = require('../assets/date-rows.js');
const { rowCapacity, isWithinA4OverflowTolerance } = require('../assets/a4-layout.js');
const { normalizeDecorationVisibility } = require('../assets/decor-visibility.js');

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
