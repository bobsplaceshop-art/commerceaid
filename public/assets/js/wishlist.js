/* =====================================================================
 * Wishlist JS
 * =====================================================================
 * Progressive enhancement for the wishlist heart button. If this
 * script fails to load, the heart does nothing - which is not a
 * functional regression, because the wishlist is a convenience,
 * not a required path. The account page (/account/wishlist) still
 * works as a page-load view of the same data.
 *
 * Behavior:
 *   - Delegated click on [data-sf-card-action="wishlist"]
 *   - Reads data-product-id
 *   - POSTs to /account/wishlist/toggle with Accept: application/json
 *   - Updates the button's is-active class and icon based on the
 *     JSON response's "on" field
 *   - Updates the aria-label and aria-pressed
 *   - Does not touch any other element on the page
 *
 * The same handler serves the product card heart and the product
 * detail page heart. Both use data-sf-card-action="wishlist".
 *
 * The button is not inside a form on the product card, so no
 * preventDefault is strictly required. The product detail page
 * button is also standalone. But preventDefault is called
 * defensively in case a future caller wraps a heart in a form.
 *
 * Errors are swallowed and logged. A failed toggle leaves the
 * heart in its previous state. No toast, no redirect - the
 * customer just sees that nothing happened.
 *
 * See docs/stages/008g.1_wishlist-plan.md.
 * ===================================================================== */
(function () {
    'use strict';

    var ENDPOINT = '/account/wishlist/toggle';

    /**
     * CSRF token.
     *
     * Priority order:
     *   1. <meta name="csrf-token"> - present on every storefront
     *      page (in the layout <head>). This is the canonical
     *      source and the one to trust.
     *   2. The first <input name="_csrf"> on the page - present
     *      on pages that render a form (product detail, cart,
     *      checkout). A fallback for pages that predate the meta
     *      tag.
     *   3. Empty string - the server rejects, the button stays
     *      in its previous state, and the console logs the 403.
     *
     * The meta tag always wins when present. Reading the input
     * first was the bug that made the catalog page 403: the
     * catalog has no form, so no _csrf input, so the request
     * went out with an empty token.
     */
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;

        var input = document.querySelector('input[name="_csrf"]');
        if (input && input.value) return input.value;

        return '';
    }

    /**
     * Update a button's visual and accessibility state to match
     * the current server truth.
     */
    function applyState(btn, isOn) {
        btn.classList.toggle('is-active', isOn);
        btn.setAttribute('aria-pressed', isOn ? 'true' : 'false');

        var icon = btn.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-heart-fill', isOn);
            icon.classList.toggle('bi-heart', !isOn);
        }

        // Product detail page label. Not present on the card button.
        var label = btn.querySelector('.sf-wishlist-label');
        if (label) {
            label.textContent = isOn ? 'Saved to Wishlist' : 'Add to Wishlist';
        }

        // aria-label on the card button
        var ariaLabel = btn.getAttribute('aria-label');
        if (ariaLabel === 'Add to wishlist' || ariaLabel === 'Remove from wishlist') {
            btn.setAttribute('aria-label', isOn ? 'Remove from wishlist' : 'Add to wishlist');
        }
    }

    /**
     * Send the toggle. On success, apply the returned state.
     * On failure, leave the button unchanged.
     */
    /**
     * Fire the pop animation on the button. Removes the class
     * when the animation ends so it can fire again on the next
     * click. No state change here - the pop is purely tactile
     * feedback.
     */
    function pop(btn) {
        btn.classList.remove('is-popping');
        // Force reflow so the browser sees the class removal and
        // will replay the animation when we add it back.
        void btn.offsetWidth;
        btn.classList.add('is-popping');
        setTimeout(function () {
            btn.classList.remove('is-popping');
        }, 450);
    }

    function toggle(btn) {
        var productId = parseInt(btn.getAttribute('data-product-id'), 10) || 0;
        if (productId <= 0) return;

        // Fire the pop immediately for tactile feedback, before
        // the server responds. Optimistic.
        pop(btn);

        // Prevent double-clicks while the request is in flight.
        if (btn.getAttribute('data-sf-wishlist-busy') === '1') return;
        btn.setAttribute('data-sf-wishlist-busy', '1');

        var body = new URLSearchParams();
        body.append('product_id', String(productId));
        body.append('_csrf', csrfToken());

        fetch(ENDPOINT, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (data) {
            if (!data || data.ok !== true) {
                throw new Error('Server returned not-ok');
            }
            applyState(btn, data.on === true);
        })
        .catch(function (err) {
            // Silent failure. Log for debugging. The button
            // remains in whatever state it was.
            if (window.console && console.warn) {
                console.warn('Wishlist toggle failed:', err && err.message ? err.message : err);
            }
        })
        .then(function () {
            btn.removeAttribute('data-sf-wishlist-busy');
        });
    }

    /**
     * Delegated click handler. Catches hearts rendered after
     * page load too, if any dynamic content ever renders one.
     */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-sf-card-action="wishlist"]');
        if (!btn) return;
        e.preventDefault();
        toggle(btn);
    });
})();
