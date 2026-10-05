'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { formatProgramDates } = require('../assets/date-rows.js');
const { rowCapacity, isWithinA4OverflowTolerance } = require('../assets/a4-layout.js');

test('formats two program dates as separate non-empty lines', () => {
    assert.deepEqual(formatProgramDates('2026-10-01', '2026-10-08'), ['Jueves 01 Oct.', 'Jueves 08 Oct.']);
});

test('keeps legacy rows to one line when the optional second date is absent', () => {
    assert.deepEqual(formatProgramDates('2026-10-01', ''), ['Jueves 01 Oct.']);
});

test('allows A4 content overflow up to 10 mm but blocks larger overflow', () => {
    assert.equal(isWithinA4OverflowTolerance(1123, 1123 + 37.8), true);
    assert.equal(isWithinA4OverflowTolerance(1123, 1123 + 50), false);
    assert.equal(isWithinA4OverflowTolerance(1123, 1123 + 10, 'ushers'), false);
});

test('adds 10 mm of tolerance to the estimated worship row capacity', () => {
    const design = { tableBodyFontSize: 17, logoSize: 104, footerHeight: 310, verseHeight: 170 };
    assert.equal(rowCapacity(design, 'worship'), 4);
});

test('keeps usher row capacity unchanged by the worship-only tolerance', () => {
    const design = { tableBodyFontSize: 17, logoSize: 124, footerHeight: 150, verseHeight: 170 };
    assert.equal(rowCapacity(design, 'ushers'), 20);
});
