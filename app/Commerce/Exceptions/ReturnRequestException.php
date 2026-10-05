<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Exceptions;

use RuntimeException;

/**
 * Thrown by ReturnRequestService when a request cannot be opened or
 * transitioned. The message is customer-readable.
 */
final class ReturnRequestException extends RuntimeException
{
}
