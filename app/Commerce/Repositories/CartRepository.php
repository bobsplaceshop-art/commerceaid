<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\Cart;

/**
 * Cart repository.
 *
 * Cart-level operations only. Items live in CartItemRepository.
 *
 * A customer has at most one cart per store. This is enforced by
 * the unique key uniq_com_cart_store_customer and by the logic
 * here: findOrCreateForCustomer() returns the existing active
 * cart if one exists, otherwise creates one.
 *
 * Converted carts are marked, not deleted. Their items remain
 * for analytics. A new 'active' cart is created on the next add.
 *
 * See docs/stages/008d_cart-plan.md.
 */
final class CartRepository extends CommerceRepository
{
    /**
     * Find the customer's active cart, or null if none exists.
     * Ignores converted and abandoned carts.
     */
    public function findByCustomer(int $customerId): ?Cart
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_carts`
              WHERE `store_id` = ? AND `customer_id` = ? AND `status` = 'active'
              LIMIT 1",
            [$this->storeId(), $customerId]
        );
        return $row !== null ? Cart::fromRow($row) : null;
    }

    /**
     * Return the customer's active cart, creating one if none
     * exists. This is the standard entry point for logged-in
     * cart operations.
     */
    public function findOrCreateForCustomer(int $customerId): Cart
    {
        $existing = $this->findByCustomer($customerId);
        if ($existing !== null) {
            return $existing;
        }

        $id = (int) $this->db->insert(
            "INSERT INTO `commerce_carts`
                (`store_id`, `customer_id`, `status`)
             VALUES (?, ?, 'active')",
            [$this->storeId(), $customerId]
        );

        $cart = new Cart();
        $cart->id         = $id;
        $cart->storeId    = $this->storeId();
        $cart->customerId = $customerId;
        $cart->status     = 'active';
        return $cart;
    }

    /**
     * Mark a cart as converted (i.e., an order was created from
     * it). Called by the checkout flow. The cart row is kept for
     * history; the next add by the same customer creates a new
     * active cart.
     */
    public function markConverted(int $cartId): void
    {
        $this->db->execute(
            "UPDATE `commerce_carts`
                SET `status` = 'converted'
              WHERE `id` = ? AND `store_id` = ? AND `status` = 'active'",
            [$cartId, $this->storeId()]
        );
    }
}
