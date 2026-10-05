'use strict';

(function (root) {
    const A4_OVERFLOW_TOLERANCE_PX = 37.8; // 10 mm at 96 CSS pixels per inch.

    function rowCapacity(design, templateType = 'worship') {
        const logoExtra = Math.max(0, Number(design.logoSize) - 104);
        if (templateType === 'ushers') {
            const rowHeight = Math.max(40, Number(design.tableBodyFontSize) * 2.4);
            return Math.max(1, Math.floor((1123 - 265 - logoExtra) / rowHeight));
        }
        const rowHeight = Math.max(45, Number(design.tableBodyFontSize) * 2.7);
        const available = 1123 - 300 - logoExtra - Number(design.footerHeight) - Number(design.verseHeight) - 190;
        return Math.max(1, Math.floor((available + A4_OVERFLOW_TOLERANCE_PX) / rowHeight));
    }

    function isWithinA4OverflowTolerance(clientHeight, scrollHeight, templateType = 'worship') {
        const tolerance = templateType === 'ushers' ? 0 : A4_OVERFLOW_TOLERANCE_PX;
        return scrollHeight <= clientHeight + tolerance;
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { rowCapacity, isWithinA4OverflowTolerance };
    else root.A4Layout = { rowCapacity, isWithinA4OverflowTolerance };
})(typeof window !== 'undefined' ? window : globalThis);
