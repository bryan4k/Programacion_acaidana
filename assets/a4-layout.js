'use strict';

(function (root) {
    function rowCapacity(design, templateType = 'worship') {
        if (templateType === 'ushers') {
            const rowHeight = Math.max(40, Number(design.tableBodyFontSize) * 2.4);
            return Math.max(1, Math.floor(1123 / rowHeight));
        }
        const rowHeight = Math.max(45, Number(design.tableBodyFontSize) * 2.7);
        // Decorations and fixed lead-in estimates do not replace the measured
        // physical A4 edge check performed on rendered pages.
        return Math.max(1, Math.floor(1123 / rowHeight));
    }

    function isWithinA4OverflowTolerance(clientHeight, scrollHeight, templateType = 'worship') {
        return scrollHeight <= clientHeight;
    }

    function availableVerseHeight(sheetHeight, verseTop, coordinatorContentHeight, verseBottomMargin = 0) {
        return Math.max(0, Math.floor(sheetHeight - verseTop - coordinatorContentHeight - verseBottomMargin));
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { rowCapacity, isWithinA4OverflowTolerance, availableVerseHeight };
    else root.A4Layout = { rowCapacity, isWithinA4OverflowTolerance, availableVerseHeight };
})(typeof window !== 'undefined' ? window : globalThis);
