<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * One order line inside a return request.
 *
 * The unit_refund and line_refund columns snapshot the amount
 * that will be refunded for this line if the request is approved.
 * The values are computed at request time from the order item's
 * unit price, so a later change to the product's price cannot
 * alter a request that was already opened.
 */
final class ReturnRequestItem
{
    public int $id = 0;
    public int $storeId = 0;
    public int $requestId = 0;
    public int $orderItemId = 0;
    public int $quantity = 0;
    public float $unitRefund = 0.0;
    public float $lineRefund = 0.0;
    public ?string $createdAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $i = new self();
        $i->id          = (int) $row['id'];
        $i->storeId     = (int) $row['store_id'];
        $i->requestId   = (int) $row['request_id'];
        $i->orderItemId = (int) $row['order_item_id'];
        $i->quantity    = (int) $row['quantity'];
        $i->unitRefund  = (float) $row['unit_refund'];
        $i->lineRefund  = (float) $row['line_refund'];
        $i->createdAt   = $row['created_at'] ?? null;
        return $i;
    }

    public function recomputeLineRefund(): float
    {
        return round($this->unitRefund * $this->quantity, 4);
    }
}
