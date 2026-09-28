<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Order line item DTO.
 *
 * Mirrors commerce_order_items. Every field that describes the
 * product (product_name, variant_label, sku, unit_price) is a
 * snapshot captured at order time. The order renders from these
 * even if the product is later renamed, re-priced, or deleted.
 *
 * product_id and variant_id are nullable because the FK uses
 * ON DELETE SET NULL. The snapshot survives product deletion.
 */
final class OrderItem
{
    public int $id = 0;
    public int $storeId = 0;
    public int $orderId = 0;
    public ?int $productId = null;
    public ?int $variantId = null;

    public string $productName = '';
    public ?string $variantLabel = null;
    public ?string $sku = null;

    public int $quantity = 0;
    public float $unitPrice = 0.0;
    public float $lineSubtotal = 0.0;
    public float $lineDiscount = 0.0;
    public float $lineTax = 0.0;
    public float $lineTotal = 0.0;

    public ?int $taxClassId = null;
    public ?float $taxRatePercent = null;

    public string $fulfilmentStatus = 'unfulfilled';
    public int $fulfilledQuantity = 0;
    public int $returnedQuantity = 0;

    public ?string $createdAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $i = new self();
        $i->id                = (int) $row['id'];
        $i->storeId           = (int) $row['store_id'];
        $i->orderId           = (int) $row['order_id'];
        $i->productId         = isset($row['product_id']) ? (int) $row['product_id'] : null;
        $i->variantId         = isset($row['variant_id']) ? (int) $row['variant_id'] : null;
        $i->productName       = (string) $row['product_name'];
        $i->variantLabel      = $row['variant_label'] ?? null;
        $i->sku               = $row['sku'] ?? null;
        $i->quantity          = (int) $row['quantity'];
        $i->unitPrice         = (float) $row['unit_price'];
        $i->lineSubtotal      = (float) $row['line_subtotal'];
        $i->lineDiscount      = (float) ($row['line_discount'] ?? 0.0);
        $i->lineTax           = (float) ($row['line_tax'] ?? 0.0);
        $i->lineTotal         = (float) $row['line_total'];
        $i->taxClassId        = isset($row['tax_class_id']) ? (int) $row['tax_class_id'] : null;
        $i->taxRatePercent    = isset($row['tax_rate_percent']) ? (float) $row['tax_rate_percent'] : null;
        $i->fulfilmentStatus  = (string) ($row['fulfilment_status'] ?? 'unfulfilled');
        $i->fulfilledQuantity = (int) ($row['fulfilled_quantity'] ?? 0);
        $i->returnedQuantity  = (int) ($row['returned_quantity'] ?? 0);
        $i->createdAt         = $row['created_at'] ?? null;
        return $i;
    }

    public function isFulfilled(): bool
    {
        return $this->fulfilmentStatus === 'fulfilled';
    }

    public function isReturned(): bool
    {
        return $this->fulfilmentStatus === 'returned';
    }

    public function hasTax(): bool
    {
        return $this->lineTax > 0.0;
    }

    public function outstandingQuantity(): int
    {
        return max(0, $this->quantity - $this->fulfilledQuantity);
    }
}
