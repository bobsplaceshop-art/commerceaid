<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Inventory movement DTO - one row in the immutable ledger.
 *
 * Movements are append-only. Never updated, never deleted.
 * Corrections use compensating movements (adjustment type).
 */
final class InventoryMovement
{
    public int $id = 0;
    public int $storeId = 0;
    public int $productId = 0;
    public int $variantId = 0;              // 0 = simple product
    public string $movementType = '';
    public int $quantityDelta = 0;
    public int $quantityAfter = 0;
    public ?string $referenceType = null;
    public ?int $referenceId = null;
    public ?string $reason = null;
    public ?int $createdBy = null;
    public string $createdAt = '';

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $m = new self();
        $m->id             = (int) $row['id'];
        $m->storeId        = (int) $row['store_id'];
        $m->productId      = (int) $row['product_id'];
        $m->variantId      = (int) ($row['variant_id'] ?? 0);
        $m->movementType   = (string) $row['movement_type'];
        $m->quantityDelta  = (int) $row['quantity_delta'];
        $m->quantityAfter  = (int) $row['quantity_after'];
        $m->referenceType  = $row['reference_type'] ?? null;
        $m->referenceId    = isset($row['reference_id']) ? (int) $row['reference_id'] : null;
        $m->reason         = $row['reason'] ?? null;
        $m->createdBy      = isset($row['created_by']) ? (int) $row['created_by'] : null;
        $m->createdAt      = (string) $row['created_at'];
        return $m;
    }

    public function isIncrease(): bool
    {
        return $this->quantityDelta > 0;
    }

    public function isDecrease(): bool
    {
        return $this->quantityDelta < 0;
    }

    public function isSale(): bool
    {
        return $this->movementType === 'sale';
    }

    public function isAdjustment(): bool
    {
        return $this->movementType === 'adjustment';
    }

    public function isFromOrder(): bool
    {
        return $this->referenceType === 'order';
    }
}
