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
     * CSRF token. The storefront uses a hidden _csrf input on
     * every form. We read the first one on the page. If none
     * exists (a page with no forms), the toggle will still send
     * the request - the server rejects with 419 if CSRF is
     * enforced. In practice, every storefront page that renders
     * a heart button also renders a form somewhere with a token.
     */
    function csrfToken() {
        var input = document.querySelector('input[name="_csrf"]');
        return input ? input.value : '';
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
    function toggle(btn) {
        var productId = parseInt(btn.getAttribute('data-product-id'), 10) || 0;
        if (productId <= 0) return;

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
