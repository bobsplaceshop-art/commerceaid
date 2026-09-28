/**
 * Storefront product image gallery.
 *
 * Clicking a thumbnail swaps the main product image. The
 * active thumbnail gets a highlighted border.
 *
 * Markup contract:
 *   [data-sf-gallery]                    root container
 *   [data-sf-gallery-main]               the <img> that swaps
 *   [data-sf-gallery-thumb][data-large]  each clickable thumb
 *
 * The "large" URL comes from the thumbnail itself (each
 * thumbnail carries its own large version). No client-side URL
 * construction - the server decides the URLs, the JS just
 * swaps them.
 *
 * Works on the picsum placeholders today, and continues to
 * work unchanged when real product images land.
 */
(function () {
    'use strict';

    function initGallery() {
        document.querySelectorAll('[data-sf-gallery]').forEach(function (gallery) {
            var main = gallery.querySelector('[data-sf-gallery-main]');
            var thumbs = gallery.querySelectorAll('[data-sf-gallery-thumb]');

            if (!main || !thumbs.length) {
                return;
            }

            thumbs.forEach(function (thumb) {
                thumb.addEventListener('click', function () {
                    var large = thumb.getAttribute('data-large');
                    if (!large) return;

                    main.src = large;

                    thumbs.forEach(function (t) {
                        t.classList.remove('is-active');
                    });
                    thumb.classList.add('is-active');
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGallery);
    } else {
        initGallery();
    }
})();
