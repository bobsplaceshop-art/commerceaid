/* =====================================================================
 * Checkout payment step
 * =====================================================================
 * Shows the M-Pesa phone field only when M-Pesa is the
 * selected payment method. The field carries its own value
 * through the checkout state; it never touches the shipping
 * address.
 *
 * The pattern follows country-select.js: a small
 * self-contained IIFE that reads the DOM once, attaches a
 * change listener to the payment-method radios, and syncs
 * the field's visibility and the HTML5 required attribute.
 *
 * If this script fails to load, the M-Pesa phone field stays
 * hidden. A customer who selects M-Pesa and submits without
 * a phone gets a clear server-side error and returns to this
 * step to retry. The field is not required by the browser
 * when the script does not run; the server is the source of
 * truth.
 * ===================================================================== */
(function () {
    'use strict';

    var group = document.getElementById('mpesa-phone-group');
    if (!group) return;

    var input = document.getElementById('mpesa_phone');

    function sync() {
        var selected = document.querySelector('input[name="payment_method"]:checked');
        var isMpesa = selected && selected.value === 'mpesa';

        if (isMpesa) {
            group.removeAttribute('hidden');
        } else {
            group.setAttribute('hidden', '');
        }

        if (input) {
            input.required = !!isMpesa;
        }
    }

    var radios = document.querySelectorAll('input[name="payment_method"]');
    for (var i = 0; i < radios.length; i++) {
        radios[i].addEventListener('change', sync);
    }

    sync();
})();
