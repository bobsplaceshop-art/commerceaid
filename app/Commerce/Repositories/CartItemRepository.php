<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\CartItem;

/**
 * Cart item repository.
 *
 * Items are keyed by (cart_id, product_id, variant_id). Every
 * mutation that operates on a single item takes the composite
 * key - the caller always has it, and this avoids a redundant
 * id lookup. Only updateQuantity() works by item id because the
 * caller there already has the loaded CartItem.
 *
 * Adding the same (product, variant) to a cart twice does not
 * create two rows. Callers should either:
 *   - Use findForCart() first and updateQuantity() on hit, or
 *   - Use incrementQuantity() which handles the upsert
 *
 * The repository does not enforce stock checks. That is
 * CartService's responsibility.
 *
 * See docs/stages/008d_cart-plan.md.
 */
final class CartItemRepository extends CommerceRepository
{
    /**
     * @return array<int, CartItem>
     */
    public function listForCart(int $cartId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_cart_items`
              WHERE `cart_id` = ?
              ORDER BY `id` ASC",
            [$cartId]
        );
        return array_map(fn ($r) => CartItem::fromRow($r), $rows);
    }

    public function findForCart(int $cartId, int $productId, int $variantId): ?CartItem
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_cart_items`
              WHERE `cart_id` = ? AND `product_id` = ? AND `variant_id` = ?
              LIMIT 1",
            [$cartId, $productId, $variantId]
        );
        return $row !== null ? CartItem::fromRow($row) : null;
    }

    /**
     * Insert a new cart item. The caller is responsible for
     * ensuring no row already exists for (cart_id, product_id,
     * variant_id) - the unique key would reject it otherwise.
     * Returns the new row id.
     */
    public function addItem(int $cartId, CartItem $item): int
    {
        return (int) $this->db->insert(
            "INSERT INTO `commerce_cart_items`
                (`cart_id`, `product_id`, `variant_id`, `quantity`,
                 `unit_price`, `product_name`, `variant_label`, `sku`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $cartId,
                $item->productId,
                $item->variantId,
                $item->quantity,
                $item->unitPrice,
                $item->productName,
                $item->variantLabel,
                $item->sku,
            ]
        );
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        $this->db->execute(
            "UPDATE `commerce_cart_items`
                SET `quantity` = ?
              WHERE `id` = ?",
            [$quantity, $itemId]
        );
    }

    /**
     * Add $delta to an existing item's quantity. Used by the
     * merge-on-login flow and by add-to-cart when a row already
     * exists. The delta can be negative (used for decrements).
     */
    public function incrementQuantity(int $cartId, int $productId, int $variantId, int $delta): void
    {
        $this->db->execute(
            "UPDATE `commerce_cart_items`
                SET `quantity` = GREATEST(0, `quantity` + ?)
              WHERE `cart_id` = ? AND `product_id` = ? AND `variant_id` = ?",
            [$delta, $cartId, $productId, $variantId]
        );
    }

    public function removeItem(int $cartId, int $productId, int $variantId): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_cart_items`
              WHERE `cart_id` = ? AND `product_id` = ? AND `variant_id` = ?",
            [$cartId, $productId, $variantId]
        );
    }

    public function removeAllForCart(int $cartId): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_cart_items` WHERE `cart_id` = ?",
            [$cartId]
        );
    }

    /**
     * Total number of units in the cart (sum of quantities).
     * Used for the header badge.
     */
    public function countForCart(int $cartId): int
    {
        return (int) $this->db->scalar(
            "SELECT COALESCE(SUM(`quantity`), 0)
               FROM `commerce_cart_items`
              WHERE `cart_id` = ?",
            [$cartId]
        );
    }
}
