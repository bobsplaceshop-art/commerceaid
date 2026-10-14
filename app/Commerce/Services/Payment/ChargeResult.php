<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment;

/**
 * Value object describing the outcome of a charge attempt.
 *
 * Returned by PaymentChargeService::charge(). Every field is
 * readonly.
 *
 * The four factory methods name the four states the service
 * produces. Callers match on the state rather than reading
 * the raw fields.
 *
 *   redirected - a real provider returned a URL. The
 *                controller redirects the customer there.
 *                The order's payment_status stays 'unpaid'
 *                until the webhook confirms.
 *
 *   charged    - a direct-charge provider confirmed inline.
 *                The order's payment_status is set by the
 *                caller based on the provider response.
 *
 *   offline    - the payment method has no provider
 *                (cod, bank_transfer, purchase_order,
 *                manual). The order is placed; nothing to
 *                call.
 *
 *   failed     - the provider returned an error. The order
 *                stays 'unpaid'. The controller flashes the
 *                error and keeps the customer on the
 *                confirm page.
 *
 * Stage 010 Session 3.
 */
final class ChargeResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $orderId,
        public readonly string $providerKey,
        public readonly ?string $redirectUrl,
        public readonly ?string $providerReference,
        public readonly string $status,
        public readonly ?string $errorMessage = null
    ) {
    }

    public static function redirected(
        int $orderId,
        string $providerKey,
        string $redirectUrl,
        string $providerReference
    ): self {
        return new self(
            true,
            $orderId,
            $providerKey,
            $redirectUrl,
            $providerReference,
            'redirected'
        );
    }

    public static function charged(
        int $orderId,
        string $providerKey,
        string $providerReference
    ): self {
        return new self(
            true,
            $orderId,
            $providerKey,
            null,
            $providerReference,
            'charged'
        );
    }

    public static function offline(
        int $orderId,
        string $providerKey
    ): self {
        return new self(
            true,
            $orderId,
            $providerKey !== '' ? $providerKey : 'offline',
            null,
            null,
            'offline'
        );
    }

    public static function failed(
        int $orderId,
        string $providerKey,
        string $errorMessage
    ): self {
        return new self(
            false,
            $orderId,
            $providerKey,
            null,
            null,
            'failed',
            $errorMessage
        );
    }

    public function isRedirected(): bool
    {
        return $this->status === 'redirected';
    }

    public function isOffline(): bool
    {
        return $this->status === 'offline';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
