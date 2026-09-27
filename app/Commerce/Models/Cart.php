<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Cart DTO.
 *
 * One cart per customer per store. Created lazily on first add
 * for a logged-in customer. Guest carts live in the customer
 * session and never touch this model.
 *
 * The status enum tracks the cart's lifecycle:
 *   'active'    - normal, in use
 *   'converted' - became an order (Stage 008E)
 *   'abandoned' - marked by a future cleanup job
 *
 * Only one cart per (store, customer) exists at a time. When a
 * cart converts to an order, the next add creates a fresh
 * 'active' cart.
 *
 * See docs/stages/008d_cart-plan.md.
 */
final class Cart
{
    public int $id = 0;
    public int $storeId = 0;
    public int $customerId = 0;
    public string $status = 'active';
    public ?string $expiresAt = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id         = (int) $row['id'];
        $c->storeId    = (int) $row['store_id'];
        $c->customerId = (int) $row['customer_id'];
        $c->status     = (string) ($row['status'] ?? 'active');
        $c->expiresAt  = $row['expires_at'] ?? null;
        $c->createdAt  = $row['created_at'] ?? null;
        $c->updatedAt  = $row['updated_at'] ?? null;
        return $c;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function isAbandoned(): bool
    {
        return $this->status === 'abandoned';
    }
}
