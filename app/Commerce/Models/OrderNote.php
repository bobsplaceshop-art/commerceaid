<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Order internal note.
 *
 * Mirrors commerce_order_notes. Append-only: rows are never
 * updated or deleted. The admin writes notes; the order detail
 * view reads them newest-first.
 *
 * is_customer_visible is false for internal notes. It exists
 * in the schema so a future storefront-facing note (e.g.
 * "your order shipped with tracking XYZ") can share the same
 * table. Nothing writes true today.
 */
final class OrderNote
{
    public int $id = 0;
    public int $storeId = 0;
    public int $orderId = 0;
    public string $note = '';
    public bool $isCustomerVisible = false;
    public ?int $createdBy = null;
    public ?string $createdAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $n = new self();
        $n->id                = (int) $row['id'];
        $n->storeId           = (int) $row['store_id'];
        $n->orderId           = (int) $row['order_id'];
        $n->note              = (string) $row['note'];
        $n->isCustomerVisible = (bool) ($row['is_customer_visible'] ?? false);
        $n->createdBy         = isset($row['created_by']) ? (int) $row['created_by'] : null;
        $n->createdAt         = $row['created_at'] ?? null;
        return $n;
    }
}
