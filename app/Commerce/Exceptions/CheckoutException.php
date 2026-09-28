<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Exceptions;

use RuntimeException;

/**
 * Raised when a checkout operation cannot be completed for a
 * reason the customer should see: empty cart, blocked line
 * (price changed, out of stock, product removed), stock race,
 * missing legal pages, invalid payment method, missing PO
 * number for a B2B order.
 *
 * The message on this exception is safe to show to the customer
 * verbatim. It is written to be human-readable and does not
 * leak internal details.
 *
 * The CheckoutController catches CheckoutException and flashes
 * the message, then redirects to the step where the customer
 * can fix the problem (usually cart review or the payment
 * step). Any other exception propagates to the error handler.
 *
 * See docs/stages/008e_checkout-plan.md.
 */
final class CheckoutException extends RuntimeException
{
}
