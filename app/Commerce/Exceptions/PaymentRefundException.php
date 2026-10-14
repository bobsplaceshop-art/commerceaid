<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Exceptions;

use RuntimeException;

/**
 * Thrown by PaymentRefundService when a refund cannot be
 * dispatched to the payment provider, or when the provider
 * returns an error.
 *
 * Distinct from ReturnRequestException, which signals that
 * the return is not in a refundable state (a workflow
 * problem). PaymentRefundException signals that the intent
 * was recorded but the money movement failed (a provider
 * problem). Callers catch them separately.
 *
 * The message is admin-readable. It names the provider and
 * the failure reason. The customer never sees it directly;
 * the admin queue's flash message reads the exception's
 * message.
 */
final class PaymentRefundException extends RuntimeException
{
}
