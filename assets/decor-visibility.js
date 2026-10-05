'use strict';

(function (root) {
    const DECORATION_VISIBILITY_KEYS = ['logo', 'headerLeft', 'headerRight', 'headerCenter', 'watermark', 'footer', 'verse'];

    function normalizeDecorationVisibility(value) {
        const source = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
        return Object.fromEntries(DECORATION_VISIBILITY_KEYS.map((key) => [
            key,
            typeof source[key] === 'boolean' ? source[key] : true,
        ]));
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { normalizeDecorationVisibility };
    else root.DecorVisibility = { normalizeDecorationVisibility };
})(typeof window !== 'undefined' ? window : globalThis);
