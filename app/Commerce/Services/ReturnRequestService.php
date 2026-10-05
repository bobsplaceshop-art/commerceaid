<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Core\Database;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Exceptions\ReturnRequestException;
use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Models\OrderItem;
use CorporateAid\Commerce\Models\ReturnRequest;
use CorporateAid\Commerce\Models\ReturnRequestItem;
use CorporateAid\Commerce\Repositories\OrderItemRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\ReturnRequestItemRepository;
use CorporateAid\Commerce\Repositories\ReturnRequestRepository;
use CorporateAid\Commerce\Repositories\StoreRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Platform\Support\CodeGenerator;

/**
 * The sole writer to commerce_return_requests and
 * commerce_return_request_items.
 *
 * Enforces the return window, the one-open-request-per-order rule,
 * the outstanding-quantity rule per line, and the refund arithmetic.
 */
final class ReturnRequestService
{
    private const VALID_REASONS = [
        'damaged',
        'wrong_item',
        'not_as_described',
        'changed_mind',
    ];

    private const GERMAN_MIN_DAYS = 14;

    public function __construct(
        private Database $db,
        private StoreContext $storeContext,
        private OrderRepository $orders,
        private OrderItemRepository $orderItems,
        private ReturnRequestRepository $requests,
        private ReturnRequestItemRepository $items,
        private StoreSettingsRepository $settings,
        private StoreRepository $stores,
        private CodeGenerator $codes
    ) {
    }

    /**
     * Open a new return request.
     *
     * @param array<int,int> $lineQuantities map of order_item_id => quantity
     * @return array{id:int, requestNumber:string, status:string}
     */
    public function open(
        int $orderId,
        int $customerId,
        array $lineQuantities,
        string $reasonCode,
        ?string $reasonNote = null
    ): array {
        if (!in_array($reasonCode, self::VALID_REASONS, true)) {
            throw new ReturnRequestException('Invalid return reason.');
        }

        $order = $this->orders->findById($orderId);
        if ($order === null || $order->customerId !== $customerId) {
            throw new ReturnRequestException('Order not found.');
        }

        if ($this->requests->hasOpenRequest($orderId)) {
            throw new ReturnRequestException(
                'You already have an open return for this order. ' .
                'Please wait for it to be reviewed or cancel it first.'
            );
        }

        if (!$this->isWithinWindow($order)) {
            throw new ReturnRequestException(
                'The return window for this order has closed.'
            );
        }

        if (empty($lineQuantities)) {
            throw new ReturnRequestException('Select at least one item to return.');
        }

        $orderItemsById = [];
        foreach ($this->orderItems->forOrder($orderId) as $item) {
            $orderItemsById[$item->id] = $item;
        }

        $linesToInsert = [];
        $totalRefund   = 0.0;

        foreach ($lineQuantities as $orderItemId => $qty) {
            $orderItemId = (int) $orderItemId;
            $qty         = (int) $qty;

            if ($qty <= 0) continue;

            if (!isset($orderItemsById[$orderItemId])) {
                throw new ReturnRequestException(
                    'One of the selected items is not part of this order.'
                );
            }

            $orderItem   = $orderItemsById[$orderItemId];
            $outstanding = $this->outstandingQuantity($orderItem);

            if ($qty > $outstanding) {
                throw new ReturnRequestException(
                    "You can return at most {$outstanding} of " .
                    "\"{$orderItem->productName}\"."
                );
            }

            $unitRefund = round($orderItem->lineTotal / max(1, $orderItem->quantity), 4);
            $lineRefund = round($unitRefund * $qty, 4);

            $linesToInsert[] = [
                'request_id'    => 0,
                'order_item_id' => $orderItemId,
                'quantity'      => $qty,
                'unit_refund'   => $unitRefund,
                'line_refund'   => $lineRefund,
            ];

            $totalRefund += $lineRefund;
        }

        if (empty($linesToInsert)) {
            throw new ReturnRequestException('Select at least one item to return.');
        }

        $autoApprove = $this->settings->bool('compliance.returns.auto_approve', false);

        $this->db->beginTransaction();
        try {
            $requestNumber = $this->codes->next(
                'commerce_return_requests',
                'RMA',
                6,
                'request_number'
            );

            $requestId = $this->requests->create([
                'order_id'       => $orderId,
                'customer_id'    => $customerId,
                'request_number' => $requestNumber,
                'status'         => $autoApprove ? 'approved' : 'requested',
                'reason_code'    => $reasonCode,
                'reason_note'    => $reasonNote,
                'refund_total'   => $totalRefund,
                'reviewed_at'    => $autoApprove ? date('Y-m-d H:i:s') : null,
            ]);

            foreach ($linesToInsert as &$line) {
                $line['request_id'] = $requestId;
            }
            unset($line);

            $this->items->createMany($linesToInsert);

            $this->db->commit();

            return [
                'id'            => $requestId,
                'requestNumber' => $requestNumber,
                'status'        => $autoApprove ? 'approved' : 'requested',
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function approve(int $requestId, int $adminUserId): bool
    {
        $req = $this->requests->findById($requestId);
        if ($req === null || $req->status !== 'requested') return false;

        $this->requests->update($requestId, [
            'status'      => 'approved',
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => $adminUserId,
        ]);
        return true;
    }

    public function reject(int $requestId, int $adminUserId, string $reason): bool
    {
        $req = $this->requests->findById($requestId);
        if ($req === null || !in_array($req->status, ['requested', 'approved'], true)) {
            return false;
        }

        $this->requests->update($requestId, [
            'status'      => 'rejected',
            'admin_note'  => $reason,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => $adminUserId,
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function markReceived(int $requestId, int $adminUserId): bool
    {
        $req = $this->requests->findById($requestId);
        if ($req === null || $req->status !== 'approved') return false;

        $this->requests->update($requestId, [
            'status' => 'received',
        ]);
        return true;
    }

    /**
     * Process the refund for a received request.
     * Updates the order's refunded_total, payment_status, and
     * (if every line is fully returned) fulfilment_status.
     *
     * @return float the applied refund amount
     */
    public function processRefund(int $requestId, int $adminUserId): float
    {
        $req = $this->requests->findById($requestId);
        if ($req === null || $req->status !== 'received') {
            throw new ReturnRequestException('This return is not ready to refund.');
        }

        $order = $this->orders->findById($req->orderId);
        if ($order === null) {
            throw new ReturnRequestException('Order not found.');
        }

        $lines = $this->items->listForRequest($requestId);
        $refundAmount = 0.0;
        foreach ($lines as $line) {
            $refundAmount += $line->lineRefund;
        }
        $refundAmount = round($refundAmount, 4);

        $newRefundedTotal = round($order->refundedTotal + $refundAmount, 4);

        $newPaymentStatus = 'partially_refunded';
        if ($newRefundedTotal >= $order->grandTotal - 0.001) {
            $newPaymentStatus = 'refunded';
        }

        $this->db->beginTransaction();
        try {
            foreach ($lines as $line) {
                $this->orderItems->incrementReturned(
                    $line->orderItemId,
                    $line->quantity
                );
            }

            $allReturned = $this->allLinesReturned($req->orderId);
            $newFulfilment = $allReturned ? 'returned' : $order->fulfilmentStatus;

            $this->orders->update($order->id, [
                'refunded_total'    => $newRefundedTotal,
                'payment_status'    => $newPaymentStatus,
                'fulfilment_status' => $newFulfilment,
            ]);

            $this->requests->update($requestId, [
                'status'       => 'refunded',
                'resolved_at'  => date('Y-m-d H:i:s'),
                'refund_total' => $refundAmount,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $refundAmount;
    }

    public function cancel(int $requestId, int $customerId): bool
    {
        $req = $this->requests->findById($requestId);
        if ($req === null || $req->customerId !== $customerId) return false;
        if (!in_array($req->status, ['requested', 'approved'], true)) return false;

        $this->requests->update($requestId, [
            'status'      => 'cancelled',
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function isWithinWindow(Order $order): bool
    {
        $closesAt = $this->windowClosesAt($order);
        if ($closesAt === null) {
            return $this->settings->bool('compliance.returns.allow_before_delivery', true);
        }
        return time() <= strtotime($closesAt);
    }

    public function windowClosesAt(Order $order): ?string
    {
        $deliveredAt = $this->deliveredAt($order->id);
        if ($deliveredAt === null) return null;

        $ts = strtotime($deliveredAt . ' +' . $this->windowDays() . ' days');
        if ($ts === false) return null;

        return date('Y-m-d H:i:s', $ts);
    }

    public function windowDays(): int
    {
        $raw = $this->settings->int('compliance.returns.window_days', 14);
        if ($raw <= 0) $raw = 14;

        $store = $this->stores->findById($this->storeContext->id());
        $country = $store?->countryCode ?? '';

        if ($country === 'DE' && $raw < self::GERMAN_MIN_DAYS) {
            return self::GERMAN_MIN_DAYS;
        }
        return $raw;
    }

    /**
     * @return array<int, ReturnRequest>
     */
    public function listForCustomer(int $customerId): array
    {
        return $this->requests->listForCustomer($customerId);
    }

    /**
     * @return array<int, ReturnRequestItem>
     */
    public function itemsFor(int $requestId): array
    {
        return $this->items->listForRequest($requestId);
    }

    public function outstandingQuantity(OrderItem $item): int
    {
        $inFlight = $this->items->sumQuantityForOrderItem($item->id);
        return max(0, $item->quantity - $item->returnedQuantity - $inFlight);
    }

    private function allLinesReturned(int $orderId): bool
    {
        foreach ($this->orderItems->forOrder($orderId) as $item) {
            if ($item->returnedQuantity < $item->quantity) {
                return false;
            }
        }
        return true;
    }

    private function deliveredAt(int $orderId): ?string
    {
        $row = $this->db->selectOne(
            "SELECT `created_at` FROM `commerce_order_status_history`
              WHERE `store_id` = ? AND `order_id` = ?
                AND `to_status` = 'delivered'
              ORDER BY `id` ASC LIMIT 1",
            [$this->storeContext->id(), $orderId]
        );
        return isset($row['created_at']) ? (string) $row['created_at'] : null;
    }
}
