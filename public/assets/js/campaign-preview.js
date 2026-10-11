/**
 * Campaign preview iframe auto-resize (parent side).
 *
 * The campaign detail page renders a sandboxed iframe
 * pointing at /commerce/ca-admin/campaigns/{id}/preview?raw=1.
 * The raw document posts its content height back here and
 * this script sets the iframe height. No scrollbars. No
 * loop, because the raw document measures body.scrollHeight
 * (stable regardless of the iframe viewport) and coalesces
 * ResizeObserver fires in a rAF tick.
 */
(function () {
    'use strict';

    function init() {
        var iframes = document.querySelectorAll('[data-campaign-preview]');
        if (!iframes.length) return;

        window.addEventListener('message', function (e) {
            var matched = null;
            iframes.forEach(function (f) {
                if (f.contentWindow === e.source) matched = f;
            });
            if (!matched) return;

            var data = e.data;
            if (!data || data.type !== 'campaign-preview:height') return;

            var h = parseInt(data.height, 10);
            if (!h || h <= 0) return;

            matched.style.height = (h + 8) + 'px';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
