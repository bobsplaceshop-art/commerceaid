<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment;

use CorporateAid\Commerce\Exceptions\PaymentRefundException;
use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\PaymentRefundRepository;
use CorporateAid\Commerce\Repositories\ReturnRequestRepository;
use CorporateAid\Commerce\Services\ReturnRequestService;
use CorporateAid\Platform\Support\Logger;

/**
 * The refund wrapper.
 *
 * Consumes ReturnRequestService::processRefund(), which writes
 * the intent (order totals, per-line returned quantities, the
 * return request's status) inside one transaction. Then it
 * dispatches the provider call post-commit.
 *
 * ReturnRequestService is not modified. It stays a pure-database
 * method. The provider call - a network round trip - must not
 * run inside a database transaction, so the split lives here.
 *
 * Three outcomes beyond the processRefund() call:
 *
 *   1. Offline method. The order's payment_provider_key is
 *      cod, bank_transfer, purchase_order, manual, or empty.
 *      No provider call. The refund row is inserted as
 *      succeeded with no provider_refund_id. The intent
 *      recorded on the order IS the record.
 *
 *   2. Provider not yet shipped. The key names a provider
 *      (stripe, paypal) but the registry returns null - the
 *      class does not exist in the codebase yet. The refund
 *      row is inserted as pending. The retry cron or a future
 *      session dispatches when the class lands.
 *
 *   3. Provider resolves. The refund row is inserted as
 *      pending, Provider::refund() is called. On success the
 *      row becomes succeeded and the order's refunded_total
 *      and payment_status are re-derived from the sum of
 *      succeeded refund rows. On failure the row becomes
 *      failed with the provider's message; the retry cron
 *      picks it up.
 *
 * Stage 010 Session 2.
 */
final class PaymentRefundService
{
    /**
     * Payment provider keys handled without a provider call.
     * The order was not charged through an external API; the
     * refund is a bookkeeping entry against the order.
     *
     * @var array<int, string>
     */
    private const OFFLINE_KEYS = [
        'cod',
        'bank_transfer',
        'purchase_order',
        'manual',
        '',
    ];

    public function __construct(
        private ReturnRequestService $returns,
        private ReturnRequestRepository $returnRequests,
        private PaymentRefundRepository $refunds,
        private OrderRepository $orders,
        private PaymentProviderRegistry $providers,
    ) {
    }

    /**
     * Process a refund for a return request.
     *
     * @throws PaymentRefundException when the wrapper cannot
     *         proceed. The admin controller catches this and
     *         flashes the message.
     */
    public function refund(int $returnRequestId, int $adminUserId): RefundResult
    {
        // Step 1. Write the intent. This commits its own
        // transaction and returns the refund amount.
        $amount = $this->returns->processRefund($returnRequestId, $adminUserId);

        // Step 2. Resolve the order for the provider key,
        // currency, and grand total.
        $orderId = $this->orderIdForReturnRequest($returnRequestId);
        if ($orderId <= 0) {
            throw new PaymentRefundException(
                'The return request has no order attached.'
            );
        }
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new PaymentRefundException('The order for this return no longer exists.');
        }

        $providerKey  = (string) ($order->paymentProviderKey ?? '');
        $currencyCode = $order->currencyCode;
        $idempotency  = $this->newIdempotencyKey();

        // Step 3a. Offline method: no provider call.
        if (in_array($providerKey, self::OFFLINE_KEYS, true)) {
            return $this->recordOffline(
                $order,
                $returnRequestId,
                $amount,
                $currencyCode,
                $idempotency,
                $providerKey
            );
        }

        // Step 3b. Provider key names a real provider. Resolve
        // it. If the class has not shipped yet, the row goes
        // in as pending and the cron retries later.
        $provider = $this->providers->resolve($providerKey);
        if ($provider === null) {
            return $this->recordPending(
                $order,
                $returnRequestId,
                $amount,
                $currencyCode,
                $idempotency,
                $providerKey
            );
        }

        // Step 3c. Dispatch the provider call. The row is
        // written first so a fatal error mid-call leaves a
        // pending record behind.
        $refundId = $this->refunds->create([
            'order_id'            => $order->id,
            'return_request_id'   => $returnRequestId,
            'provider_key'        => $providerKey,
            'provider_payment_id' => $order->paymentReference,
            'amount'              => $amount,
            'currency_code'       => $currencyCode,
            'reason'              => 'Return ' . $returnRequestId,
            'idempotency_key'     => $idempotency,
        ]);

        $result = $provider->refund($order, $amount, $idempotency);

        if ($result['ok'] === true) {
            $providerRefundId = (string) ($result['refund_id'] ?? '');
            $this->refunds->markSucceeded($refundId, $providerRefundId);
            $this->rederiveOrderTotals($order);

            return RefundResult::succeeded(
                $refundId,
                $order->id,
                $returnRequestId,
                $providerKey,
                $providerRefundId,
                $amount,
                $currencyCode,
                $idempotency
            );
        }

        $errorMessage = (string) ($result['error'] ?? 'Provider returned an error.');
        $this->refunds->markFailed($refundId, $errorMessage);

        return RefundResult::failed(
            $refundId,
            $order->id,
            $returnRequestId,
            $providerKey,
            $amount,
            $currencyCode,
            $idempotency,
            $errorMessage
        );
    }

    /**
     * Retry a failed refund row. Called by bin/retry-refunds.php.
     * On success the order totals are re-derived.
     *
     * @throws PaymentRefundException when the row cannot be
     *         retried (not found, not failed, provider missing).
     */
    public function retry(int $refundId): RefundResult
    {
        $row = $this->refunds->findById($refundId);
        if ($row === null) {
            throw new PaymentRefundException('Refund row not found.');
        }
        if (($row['status'] ?? '') !== 'failed') {
            throw new PaymentRefundException('Only failed refunds can be retried.');
        }

        $orderId = (int) ($row['order_id'] ?? 0);
        $order   = $this->orders->findById($orderId);
        if ($order === null) {
            throw new PaymentRefundException('The order for this refund no longer exists.');
        }

        $providerKey = (string) ($row['provider_key'] ?? '');
        $provider    = $this->providers->resolve($providerKey);
        if ($provider === null) {
            throw new PaymentRefundException(
                "The provider \"{$providerKey}\" is not available."
            );
        }

        $amount       = (float) ($row['amount'] ?? 0.0);
        $currencyCode = (string) ($row['currency_code'] ?? $order->currencyCode);
        $idempotency  = (string) ($row['idempotency_key'] ?? '');
        $returnId     = (int) ($row['return_request_id'] ?? 0);

        $result = $provider->refund($order, $amount, $idempotency);

        if ($result['ok'] === true) {
            $providerRefundId = (string) ($result['refund_id'] ?? '');
            $this->refunds->markSucceeded($refundId, $providerRefundId);
            $this->rederiveOrderTotals($order);

            return RefundResult::succeeded(
                $refundId,
                $order->id,
                $returnId,
                $providerKey,
                $providerRefundId,
                $amount,
                $currencyCode,
                $idempotency
            );
        }

        $errorMessage = (string) ($result['error'] ?? 'Provider returned an error.');
        $this->refunds->markFailed($refundId, $errorMessage);

        return RefundResult::failed(
            $refundId,
            $order->id,
            $returnId,
            $providerKey,
            $amount,
            $currencyCode,
            $idempotency,
            $errorMessage
        );
    }

    /**
     * Insert the refund row as succeeded with no provider id.
     * Offline methods have no external money movement to
     * confirm.
     */
    private function recordOffline(
        Order $order,
        int $returnRequestId,
        float $amount,
        string $currencyCode,
        string $idempotency,
        string $providerKey
    ): RefundResult {
        $key = $providerKey !== '' ? $providerKey : 'offline';
        $refundId = $this->refunds->create([
            'order_id'            => $order->id,
            'return_request_id'   => $returnRequestId,
            'provider_key'        => $key,
            'provider_payment_id' => $order->paymentReference,
            'amount'              => $amount,
            'currency_code'       => $currencyCode,
            'reason'              => 'Return ' . $returnRequestId,
            'idempotency_key'     => $idempotency,
        ]);
        $this->refunds->markSucceeded($refundId, '');

        return RefundResult::offline(
            $refundId,
            $order->id,
            $returnRequestId,
            $key,
            $amount,
            $currencyCode,
            $idempotency
        );
    }

    /**
     * Insert the refund row as pending. The provider class has
     * not shipped, so no call happens yet. The retry cron
     * picks the row up once the class exists.
     */
    private function recordPending(
        Order $order,
        int $returnRequestId,
        float $amount,
        string $currencyCode,
        string $idempotency,
        string $providerKey
    ): RefundResult {
        $refundId = $this->refunds->create([
            'order_id'            => $order->id,
            'return_request_id'   => $returnRequestId,
            'provider_key'        => $providerKey,
            'provider_payment_id' => $order->paymentReference,
            'amount'              => $amount,
            'currency_code'       => $currencyCode,
            'reason'              => 'Return ' . $returnRequestId,
            'idempotency_key'     => $idempotency,
        ]);

        Logger::info('payment.refund.pending', [
            'refund_id' => $refundId,
            'order_id'  => $order->id,
            'provider'  => $providerKey,
            'reason'    => 'provider class not resolved',
        ]);

        return RefundResult::pending(
            $refundId,
            $order->id,
            $returnRequestId,
            $providerKey,
            $amount,
            $currencyCode,
            $idempotency
        );
    }

    /**
     * Recompute the order's refunded_total and payment_status
     * from the sum of succeeded refund rows. Called after a
     * successful provider call. processRefund() already wrote
     * a value; this corrects it against the table that is the
     * source of truth for what actually moved.
     */
    private function rederiveOrderTotals(Order $order): void
    {
        $total = $this->refunds->sumSucceededForOrder($order->id);

        $status = $total >= $order->grandTotal - 0.001
            ? 'refunded'
            : 'partially_refunded';

        // Never overwrite a full refund with a partial one.
        // The sum of succeeded rows is authoritative; the
        // guard protects against a later failed refund
        // attempt subtracting from a fully-refunded order.
        if ($order->paymentStatus === 'refunded' && $status === 'partially_refunded') {
            return;
        }

        $this->orders->update($order->id, [
            'refunded_total' => $total,
            'payment_status' => $status,
        ]);
    }

    /**
     * The order id attached to a return request.
     */
    private function orderIdForReturnRequest(int $returnRequestId): int
    {
        $req = $this->returnRequests->findById($returnRequestId);
        return $req !== null ? $req->orderId : 0;
    }

    private function newIdempotencyKey(): string
    {
        return 'rf_' . bin2hex(random_bytes(16));
    }
}
