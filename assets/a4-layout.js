'use strict';

(function (root) {
    function rowCapacity(design, templateType = 'worship') {
        if (templateType === 'ushers') {
            const rowHeight = Math.max(40, Number(design.tableBodyFontSize) * 2.4);
            return Math.max(1, Math.floor((1123 - 265) / rowHeight));
        }
        const rowHeight = Math.max(45, Number(design.tableBodyFontSize) * 2.7);
        // Reserve only the fixed header/table lead-in. Footer, verse, logo, and
        // padding are visual elements inside the sheet, not page-edge limits.
        return Math.max(1, Math.floor((1123 - 300) / rowHeight));
    }

    function isWithinA4OverflowTolerance(clientHeight, scrollHeight, templateType = 'worship') {
        return scrollHeight <= clientHeight;
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { rowCapacity, isWithinA4OverflowTolerance };
    else root.A4Layout = { rowCapacity, isWithinA4OverflowTolerance };
})(typeof window !== 'undefined' ? window : globalThis);
