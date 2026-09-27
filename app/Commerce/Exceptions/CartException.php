<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Exceptions;

use RuntimeException;

/**
 * Raised when a cart operation cannot be completed for a reason
 * the customer should see: product not found, not published,
 * insufficient stock, variant mismatch, etc.
 *
 * The message on this exception is safe to show to the customer
 * verbatim. It is written to be human-readable and does not leak
 * internal details.
 *
 * The CartController catches CartException and either flashes
 * the message (form POST) or returns it as JSON (AJAX). Any
 * other exception propagates to the error handler.
 *
 * See docs/stages/008d_cart-plan.md.
 */
final class CartException extends RuntimeException
{
}
