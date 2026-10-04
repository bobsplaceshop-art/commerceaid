/* ------------------------------------------------------------------
 * Product detail tabs.
 *
 * Description | Specifications | Reviews. Tab buttons carry
 * data-sf-tab="{key}". Panels carry data-sf-tab-panel="{key}".
 * Click swaps .is-active and updates aria-selected.
 *
 * No dependencies. Accessible: keyboard focus on tabs, aria
 * attributes kept in sync on every change.
 *
 * Progressive enhancement: without JS, all three panels render
 * (because .sf-product-tab-panel has display: block when the
 * stylesheet is not loaded — actually that is not true, the
 * rule sets display: none). To ensure content is visible
 * without JS, the active panel is server-rendered. Non-active
 * panels stay hidden, which is the same behavior as with JS.
 * ---------------------------------------------------------------- */
(function () {
    'use strict';

    function boot() {
        var root = document.querySelector('[data-sf-tabs]');
        if (!root) return;

        var tabs   = Array.prototype.slice.call(
            root.querySelectorAll('[data-sf-tab]')
        );
        var panels = Array.prototype.slice.call(
            root.querySelectorAll('[data-sf-tab-panel]')
        );

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var key = tab.getAttribute('data-sf-tab');

                tabs.forEach(function (t) {
                    var active = t === tab;
                    t.classList.toggle('is-active', active);
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                panels.forEach(function (p) {
                    var active = p.getAttribute('data-sf-tab-panel') === key;
                    p.classList.toggle('is-active', active);
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
