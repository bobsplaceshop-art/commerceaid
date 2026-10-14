<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment;

/**
 * Value object describing the outcome of a refund attempt.
 *
 * Returned by PaymentRefundService::refund(). Every field is
 * readonly. Every field is either set by the service from
 * the provider's response or from the commerce_payment_refunds
 * row.
 *
 * The four factory methods name the four states the service
 * produces. Callers match on the state rather than reading
 * the raw fields.
 */
final class RefundResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $refundId,
        public readonly int $orderId,
        public readonly int $returnRequestId,
        public readonly string $providerKey,
        public readonly ?string $providerRefundId,
        public readonly float $amount,
        public readonly string $currencyCode,
        public readonly string $status,
        public readonly string $idempotencyKey,
        public readonly ?string $errorMessage = null
    ) {
    }

    /**
     * The provider confirmed the refund. The commerce_payment_refunds
     * row's status is 'succeeded' and the order's refunded_total has
     * been updated.
     */
    public static function succeeded(
        int $refundId,
        int $orderId,
        int $returnRequestId,
        string $providerKey,
        string $providerRefundId,
        float $amount,
        string $currencyCode,
        string $idempotencyKey
    ): self {
        return new self(
            true,
            $refundId,
            $orderId,
            $returnRequestId,
            $providerKey,
            $providerRefundId,
            $amount,
            $currencyCode,
            'succeeded',
            $idempotencyKey
        );
    }

    /**
     * The refund row was written. The provider call is in
     * flight or has been queued for retry. The order's
     * refunded_total is not yet updated for this refund.
     */
    public static function pending(
        int $refundId,
        int $orderId,
        int $returnRequestId,
        string $providerKey,
        float $amount,
        string $currencyCode,
        string $idempotencyKey
    ): self {
        return new self(
            false,
            $refundId,
            $orderId,
            $returnRequestId,
            $providerKey,
            null,
            $amount,
            $currencyCode,
            'pending',
            $idempotencyKey
        );
    }

    /**
     * The provider returned an error. The refund row's status
     * is 'failed' with the provider's message. The retry cron
     * picks the row up.
     */
    public static function failed(
        int $refundId,
        int $orderId,
        int $returnRequestId,
        string $providerKey,
        float $amount,
        string $currencyCode,
        string $idempotencyKey,
        string $errorMessage
    ): self {
        return new self(
            false,
            $refundId,
            $orderId,
            $returnRequestId,
            $providerKey,
            null,
            $amount,
            $currencyCode,
            'failed',
            $idempotencyKey,
            $errorMessage
        );
    }

    /**
     * The order's payment_provider_key is an offline method
     * (cod, bank_transfer, purchase_order). No provider call
     * happens. The intent recorded on the order is the record
     * of what was refunded. The refund row is 'succeeded'
     * without a provider_refund_id.
     */
    public static function offline(
        int $refundId,
        int $orderId,
        int $returnRequestId,
        string $providerKey,
        float $amount,
        string $currencyCode,
        string $idempotencyKey
    ): self {
        return new self(
            true,
            $refundId,
            $orderId,
            $returnRequestId,
            $providerKey,
            null,
            $amount,
            $currencyCode,
            'succeeded',
            $idempotencyKey
        );
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
