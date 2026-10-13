/**
 * Filter bar toggle.
 *
 * On mobile widths the .ra-filter-bar hides by default. The
 * .ra-filter-toggle button (a sibling of the bar) becomes
 * visible and toggles .is-open on the nearest .ra-filter-bar.
 *
 * One delegated listener handles every filter form on every
 * admin list page. No per-page wiring.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ra-filter-toggle]');
        if (!btn) return;

        // Find the next .ra-filter-bar sibling, or the nearest
        // one inside the same card.
        var bar = btn.parentElement
            ? btn.parentElement.querySelector('.ra-filter-bar')
            : null;

        if (!bar) {
            // Fallback: search forward from the button.
            var next = btn.nextElementSibling;
            while (next && !next.classList.contains('ra-filter-bar')) {
                next = next.nextElementSibling;
            }
            bar = next;
        }

        if (!bar) return;

        bar.classList.toggle('is-open');
    });
})();
