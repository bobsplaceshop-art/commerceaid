<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Reads and writes commerce_pesapal_requests.
 *
 * One row per Pesapal order submitted. The row carries
 * the order_tracking_id Pesapal returned, the order it
 * was created for, and the pending / completed / failed
 * state.
 *
 * The provider (PesapalProvider) writes the row from
 * charge(). The webhook controller reads it from
 * verifyWebhook() to confirm the IPN's order_tracking_id
 * matches a request this store initiated. The handler
 * calls GetTransactionStatus and updates the row with the
 * confirmation code when the payment succeeds.
 *
 * Pesapal does not sign its IPNs. This table is the
 * verification mechanism; an IPN whose order_tracking_id
 * is not in this table is a forgery or a replay and is
 * refused.
 *
 * The cleanup cron (bin/cleanup-pesapal-requests.php)
 * calls deleteStale(). Rows stay at most 24 hours past
 * their creation while still pending.
 *
 * Stage 010, sub-stage 010.2.
 */
final class PesapalRequestRepository extends CommerceRepository
{
    /**
     * Insert a pending request row. Returns the new id.
     *
     * Called by PesapalProvider::charge() after
     * SubmitOrderRequest returns an order_tracking_id and a
     * redirect_url. The row is the record that an order is
     * in flight.
     *
     * @param array{
     *     order_id: int,
     *     order_tracking_id: string,
     *     merchant_reference: string,
     *     amount: float,
     *     currency_code: string
     * } $data
     */
    public function record(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_pesapal_requests`
                (`store_id`, `order_id`, `order_tracking_id`,
                 `merchant_reference`, `amount`, `currency_code`,
                 `status`)
             VALUES (?, ?, ?, ?, ?, ?, 'pending')",
            [
                $this->storeId(),
                (int) $data['order_id'],
                (string) $data['order_tracking_id'],
                (string) $data['merchant_reference'],
                (float) $data['amount'],
                strtoupper((string) $data['currency_code']),
            ]
        );
    }

    /**
     * Look up a request by its Pesapal-issued
     * order_tracking_id. Returns null when the id is
     * unknown for this store.
     *
     * This is the verification path. An IPN whose
     * order_tracking_id is not in this table is refused.
     *
     * @return array<string,mixed>|null
     */
    public function findByTrackingId(string $orderTrackingId): ?array
    {
        if ($orderTrackingId === '') {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_pesapal_requests`
              WHERE `store_id` = ? AND `order_tracking_id` = ?
              LIMIT 1",
            [$this->storeId(), $orderTrackingId]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `commerce_pesapal_requests`
              WHERE `store_id` = ? AND `id` = ?
              LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /**
     * Every pending request for an order. Used by the
     * abandoned-order cleanup and the admin order detail.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listPendingForOrder(int $orderId): array
    {
        return $this->db->select(
            "SELECT * FROM `commerce_pesapal_requests`
              WHERE `store_id` = ? AND `order_id` = ?
                AND `status` = 'pending'
              ORDER BY `id` DESC",
            [$this->storeId(), $orderId]
        );
    }

    /**
     * Mark a request completed. Called by
     * PesapalProvider::handleWebhook() when
     * GetTransactionStatus reports COMPLETED.
     *
     * Stores the confirmation_code Pesapal returned so the
     * order's payment_reference can be written from the
     * same row. Also stores the payment_status_description
     * verbatim for the audit trail.
     */
    public function markCompleted(
        string $orderTrackingId,
        ?string $confirmationCode,
        ?string $paymentStatusDescription
    ): void {
        $this->db->execute(
            "UPDATE `commerce_pesapal_requests`
                SET `status` = 'completed',
                    `confirmation_code` = ?,
                    `payment_status_description` = ?
              WHERE `store_id` = ? AND `order_tracking_id` = ?",
            [
                $confirmationCode,
                $paymentStatusDescription,
                $this->storeId(),
                $orderTrackingId,
            ]
        );
    }

    /**
     * Mark a request failed. Called by
     * PesapalProvider::handleWebhook() when
     * GetTransactionStatus reports FAILED or INVALID, or
     * by the abandoned-order cleanup when the order is
     * cancelled before payment completes.
     */
    public function markFailed(
        string $orderTrackingId,
        ?string $paymentStatusDescription
    ): void {
        $this->db->execute(
            "UPDATE `commerce_pesapal_requests`
                SET `status` = 'failed',
                    `payment_status_description` = ?
              WHERE `store_id` = ? AND `order_tracking_id` = ?",
            [
                $paymentStatusDescription,
                $this->storeId(),
                $orderTrackingId,
            ]
        );
    }

    /**
     * Delete every pending row older than the cutoff.
     * Called by bin/cleanup-pesapal-requests.php.
     *
     * Rows in 'completed' or 'failed' state are never
     * deleted by this method. They stay for audit.
     *
     * @return int  the number of rows deleted
     */
    public function deleteStale(int $hours = 24): int
    {
        return $this->db->execute(
            "DELETE FROM `commerce_pesapal_requests`
              WHERE `store_id` = ?
                AND `status` = 'pending'
                AND `created_at` < (NOW() - INTERVAL ? HOUR)",
            [$this->storeId(), $hours]
        );
    }
}
