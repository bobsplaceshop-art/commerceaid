/* =====================================================================
 * Storefront hero image swap.
 * =====================================================================
 * Loaded on every storefront page. Provides a single
 * global function that both the gallery thumbnail swap
 * and the swatch swap call.
 *
 * The product detail hero is a <picture> block with
 * one <source type="image/webp"> and one
 * <source type="image/jpeg">. Setting only the <img>
 * src has no visible effect because the browser
 * prefers the <source> elements. The fix is to remove
 * the <source> elements first, then set the img src.
 *
 * Trade-off: after the swap, the hero loses its
 * srcset. That is acceptable — the user is now looking
 * at one specific chosen image, and a page reload
 * restores the full responsive <picture>. On very
 * high-DPR displays the swapped image may be slightly
 * upscaled. That is a small quality cost for a
 * straightforward, robust swap.
 *
 * Usage from anywhere:
 *
 *     window.SF.swapHeroImage('/media/uuid/lg.webp');
 *
 * Returns true when the swap happened, false when the
 * hero was not found or the URL was empty.
 * ===================================================================== */
(function () {
    'use strict';

    var SF = window.SF = window.SF || {};

    SF.swapHeroImage = function (newSrc) {
        if (!newSrc) return false;

        var heroImg = document.querySelector('[data-sf-gallery-main]');
        if (!heroImg) return false;

        var picture = heroImg.closest('picture');
        if (picture) {
            // Remove every <source> child so the browser
            // falls back to the <img src>. Also clear any
            // srcset/sizes on the <img> itself for safety
            // in case it carried one.
            var sources = picture.querySelectorAll('source');
            for (var i = 0; i < sources.length; i++) {
                sources[i].parentNode.removeChild(sources[i]);
            }
            heroImg.removeAttribute('srcset');
            heroImg.removeAttribute('sizes');
        }

        heroImg.src = newSrc;
        return true;
    };
})();
