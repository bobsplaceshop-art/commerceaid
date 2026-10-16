<?php
/**
 * M-Pesa STK push waiting page.
 *
 * Reached after CheckoutController::place() redirects an
 * M-Pesa charge here. The customer's phone is prompting
 * for a PIN. This page polls /checkout/mpesa/status every
 * 3 seconds. When the order's payment_status flips to
 * paid, the script redirects to the confirmation page.
 *
 * Context (from CheckoutController::mpesaWaiting):
 *   $order  Order
 *
 * The script is loaded from mpesa-waiting.js. If the
 * script fails to load, the customer sees the page but
 * nothing happens. The cancel link is a plain <a>, so
 * the customer always has a way out.
 */
$order = $order ?? null;

if ($order === null) {
    echo '<div class="sf-container"><p>Order not found.</p></div>';
    return;
}
?>
<div class="sf-checkout" data-sf-page="checkout-mpesa-waiting">

    <div class="sf-container">

        <div class="sf-checkout-confirmation-hero">
            <i class="bi bi-phone-fill" aria-hidden="true"></i>
            <h1 class="sf-checkout-title">Check your phone</h1>
            <p class="sf-checkout-subtitle">
                We sent a payment prompt to your phone.
                Enter your M-Pesa PIN to confirm order
                <strong><?= e($order->orderNumber) ?></strong>.
            </p>
        </div>

        <div class="sf-checkout-summary sf-checkout-summary-wide">

            <div class="sf-mpesa-waiting"
                 data-order-number="<?= e($order->orderNumber) ?>"
                 data-status-endpoint="/checkout/mpesa/status">

                <div class="sf-mpesa-waiting-spinner" aria-hidden="true"></div>

                <p class="sf-mpesa-waiting-message" aria-live="polite">
                    Waiting for confirmation&hellip;
                </p>

                <p class="sf-mpesa-waiting-total">
                    Total <strong>
                        <?= e(number_format($order->grandTotal, 2)) ?>
                        <?= e($order->currencyCode) ?>
                    </strong>
                </p>

            </div>

            <div class="sf-mpesa-waiting-help">
                <p class="muted small mb-0">
                    Did not receive the prompt?
                    <a href="/checkout/mpesa/cancel/<?= e($order->orderNumber) ?>">
                        Try another payment method
                    </a>
                </p>
            </div>

        </div>

    </div>

</div>

<script src="<?= versioned_asset('js/mpesa-waiting.js') ?>" defer></script>
