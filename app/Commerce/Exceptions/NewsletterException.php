<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Exceptions;

use RuntimeException;

/**
 * Thrown by the newsletter services when a request cannot be
 * completed. The message is user-readable (customer-facing or
 * admin-facing).
 *
 * Same shape as ReturnRequestException and CheckoutException.
 */
final class NewsletterException extends RuntimeException
{
}
