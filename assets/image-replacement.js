'use strict';

(function (root) {
    function shouldShowDefaultArtwork(customImage) {
        return !customImage;
    }

    function imageReplacementVisibility(decorationVisible, hasCustomImage) {
        return {
            customImageHidden: !decorationVisible || !hasCustomImage,
            defaultArtworkHidden: !decorationVisible || hasCustomImage,
        };
    }

    function footerDecorationVisibility(decorationVisible, hasCustomImage) {
        return {
            ...imageReplacementVisibility(decorationVisible, hasCustomImage),
            footerContentHidden: false,
        };
    }

    function isCurrentImageRead(readVersion, currentVersion) {
        return readVersion === currentVersion;
    }

    const api = { shouldShowDefaultArtwork, imageReplacementVisibility, footerDecorationVisibility, isCurrentImageRead };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.ImageReplacement = api;
})(typeof window !== 'undefined' ? window : globalThis);
