/* =====================================================================
 * M-Pesa waiting page
 * =====================================================================
 * Polls /checkout/mpesa/status every 3 seconds while the
 * customer's phone prompts for an M-Pesa PIN. When the
 * order's payment_status flips to 'paid', the page
 * redirects to the confirmation page. When it flips to
 * 'failed' or 'cancelled', the page sends the customer
 * back to the payment step.
 *
 * The polling pattern follows wishlist.js: meta csrf-token
 * source, POST with X-Requested-With: XMLHttpRequest,
 * credentials: same-origin, JSON response.
 *
 * If this script fails to load, the page renders but
 * nothing happens. The cancel link on the page is a plain
 * anchor, so the customer always has a way out.
 *
 * See docs/stages/010.1_mpesa-plan.md.
 * ===================================================================== */
(function () {
    'use strict';

    var root = document.querySelector('.sf-mpesa-waiting');
    if (!root) return;

    var orderNumber = root.getAttribute('data-order-number');
    if (!orderNumber) return;

    var endpoint = root.getAttribute('data-status-endpoint') || '/checkout/mpesa/status';
    var messageEl = root.querySelector('.sf-mpesa-waiting-message');

    var POLL_MS = 3000;
    var MAX_POLL_MS = 3 * 60 * 1000; /* give up after 3 minutes */

    var startedAt = Date.now();

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;

        var input = document.querySelector('input[name="_csrf"]');
        if (input && input.value) return input.value;

        return '';
    }

    function setMessage(text) {
        if (messageEl) {
            messageEl.textContent = text;
        }
    }

    function poll() {
        if (Date.now() - startedAt > MAX_POLL_MS) {
            setMessage('Still waiting. If you did not receive the prompt, use the link below to try another method.');
            return;
        }

        var body = new URLSearchParams();
        body.append('order_number', orderNumber);
        body.append('_csrf', csrfToken());

        fetch(endpoint, {
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
            if (!data || typeof data.status !== 'string') {
                setTimeout(poll, POLL_MS);
                return;
            }

            if (data.status === 'paid') {
                window.location.href = '/checkout/confirmation/' + encodeURIComponent(orderNumber);
                return;
            }

            if (data.status === 'failed' || data.status === 'cancelled') {
                window.location.href = '/checkout/confirm';
                return;
            }

            /* Still unpaid. Poll again. */
            setTimeout(poll, POLL_MS);
        })
        .catch(function (err) {
            /* Network blip. Retry once. If it keeps failing, the
               customer can still use the cancel link. */
            if (window.console && console.warn) {
                console.warn('M-Pesa status poll failed:', err && err.message ? err.message : err);
            }
            setTimeout(poll, 5000);
        });
    }

    /* First poll after a short delay so the customer sees the
       page render before the first request goes out. */
    setTimeout(poll, POLL_MS);
})();
