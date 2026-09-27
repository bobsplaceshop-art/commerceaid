<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Exceptions\CartException;
use CorporateAid\Commerce\Models\Cart;
use CorporateAid\Commerce\Models\CartItem;
use CorporateAid\Commerce\Models\CartLineItem;
use CorporateAid\Commerce\Repositories\CartItemRepository;
use CorporateAid\Commerce\Repositories\CartRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;
use CorporateAid\Core\Session;

/**
 * Cart service.
 *
 * The cart is stateful and lives in one of two places depending
 * on whether a customer is logged in:
 *
 *   Guest     -> $_SESSION['cart'] in the customer session
 *   Customer  -> commerce_carts + commerce_cart_items tables
 *
 * Every public method resolves the storage location internally.
 * The caller does not need to know whether it's dealing with a
 * guest or a customer.
 *
 * The mergeGuestCartOnLogin() method is called from
 * CustomerAuthService::login() right after the customer id is
 * written to the session. It moves any items in the session cart
 * into the customer's DB cart and clears the session cart.
 *
 * Stock is validated at add time via Inventory::canSell() but
 * not reserved. Actual reservation happens in the checkout
 * transaction (Stage 008E). A customer leaving items in their
 * cart does not lock stock.
 *
 * See docs/stages/008d_cart-plan.md.
 */
final class CartService
{
    private const SESSION_CART_KEY = 'cart';

    public function __construct(
        private Session $session,
        private CartRepository $carts,
        private CartItemRepository $items,
        private ProductRepository $products,
        private ProductVariantRepository $variants,
        private InventoryRepository $inventory,
        private CustomerAuthService $customerAuth,
    ) {
    }

    // =================================================================
    // Mutations
    // =================================================================

    /**
     * Add a product/variant to the cart. Validates the product
     * exists, is published, and has enough stock (unless
     * backorders are allowed). If the item is already in the
     * cart, quantity is summed.
     *
     * @throws CartException on validation failure
     */
    public function add(int $productId, int $variantId, int $qty): void
    {
        if ($qty <= 0) {
            throw new CartException('Quantity must be at least 1.');
        }

        $snapshot = $this->buildSnapshot($productId, $variantId, $qty);

        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            $this->addToCustomerCart($customer->id, $snapshot, $qty);
        } else {
            $this->addToSessionCart($snapshot, $qty);
        }
    }

    /**
     * Set an item's quantity to an exact value. A quantity of 0
     * removes the item. Validates stock for the new quantity.
     *
     * @throws CartException
     */
    public function updateQuantity(int $productId, int $variantId, int $qty): void
    {
        if ($qty < 0) {
            throw new CartException('Quantity cannot be negative.');
        }

        if ($qty === 0) {
            $this->remove($productId, $variantId);
            return;
        }

        // Re-validate stock for the new quantity.
        $this->assertStockAvailable($productId, $variantId, $qty);

        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            $cart = $this->carts->findByCustomer($customer->id);
            if ($cart === null) {
                return;
            }
            $item = $this->items->findForCart($cart->id, $productId, $variantId);
            if ($item === null) {
                return;
            }
            $this->items->updateQuantity($item->id, $qty);
        } else {
            $this->updateSessionItemQuantity($productId, $variantId, $qty);
        }
    }

    /**
     * Remove an item from the cart entirely. Idempotent.
     */
    public function remove(int $productId, int $variantId): void
    {
        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            $cart = $this->carts->findByCustomer($customer->id);
            if ($cart === null) {
                return;
            }
            $this->items->removeItem($cart->id, $productId, $variantId);
        } else {
            $this->removeSessionItem($productId, $variantId);
        }
    }

    /**
     * Empty the cart. Idempotent.
     */
    public function clear(): void
    {
        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            $cart = $this->carts->findByCustomer($customer->id);
            if ($cart !== null) {
                $this->items->removeAllForCart($cart->id);
            }
        } else {
            $this->session->forget(self::SESSION_CART_KEY);
        }
    }

    // =================================================================
    // Reads
    // =================================================================

    /**
     * Return the cart's line items as CartLineItem view-models.
     * Each line item is paired with the current live product and
     * inventory rows so the view can flag drift.
     *
     * @return array<int, CartLineItem>
     */
    public function items(): array
    {
        $rawItems = $this->rawItems();
        if ($rawItems === []) {
            return [];
        }

        // Bulk-load live products and inventory. No N+1.
        $productIds = [];
        $units      = [];
        foreach ($rawItems as $raw) {
            $productIds[$raw->productId] = true;
            $units[] = [
                'product_id' => $raw->productId,
                'variant_id' => $raw->variantId,
            ];
        }

        $liveProducts = [];
        foreach (array_keys($productIds) as $pid) {
            $p = $this->products->findById($pid);
            if ($p !== null) {
                $liveProducts[$pid] = $p;
            }
        }

        $liveInventory = $this->inventory->forUnits($units);

        $lines = [];
        foreach ($rawItems as $raw) {
            $product   = $liveProducts[$raw->productId] ?? null;
            $inv       = $liveInventory[$raw->productId . ':' . $raw->variantId] ?? null;
            $lines[]   = new CartLineItem($raw, $product, $inv);
        }
        return $lines;
    }

    /**
     * Total units across all lines. For the header badge.
     */
    public function count(): int
    {
        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            $cart = $this->carts->findByCustomer($customer->id);
            if ($cart === null) {
                return 0;
            }
            return $this->items->countForCart($cart->id);
        }

        return $this->sessionCount();
    }

    /**
     * Sum of effective line subtotals. Uses the live price if the
     * product is available, otherwise the snapshot.
     */
    public function subtotal(): float
    {
        $total = 0.0;
        foreach ($this->items() as $line) {
            $total += $line->effectiveSubtotal();
        }
        return round($total, 4);
    }

    public function isEmpty(): bool
    {
        return $this->rawItems() === [];
    }

    // =================================================================
    // Merge on login
    // =================================================================

    /**
     * Merge the guest session cart into the customer's DB cart.
     * Called from CustomerAuthService::login() right after the
     * customer id is written to the session.
     *
     * Conflict rule: items with the same (product_id, variant_id)
     * sum their quantities. New items are appended.
     *
     * The session cart is cleared after merge.
     */
    public function mergeGuestCartOnLogin(): void
    {
        $sessionItems = $this->sessionItems();
        if ($sessionItems === []) {
            return;
        }

        $customer = $this->customerAuth->user();
        if ($customer === null) {
            return;
        }

        $cart = $this->carts->findOrCreateForCustomer($customer->id);

        foreach ($sessionItems as $sessionItem) {
            $productId = (int) $sessionItem['product_id'];
            $variantId = (int) $sessionItem['variant_id'];
            $qty       = (int) $sessionItem['quantity'];

            $existing = $this->items->findForCart($cart->id, $productId, $variantId);
            if ($existing !== null) {
                // Sum quantities. Use the snapshot's unit price -
                // the customer saw that price when they added the
                // item as a guest, so we preserve it for the
                // priceChanged warning.
                $this->items->incrementQuantity($cart->id, $productId, $variantId, $qty);
            } else {
                $item = CartItem::fromSessionItem($cart->id, $sessionItem);
                $this->items->addItem($cart->id, $item);
            }
        }

        $this->session->forget(self::SESSION_CART_KEY);
    }

    // =================================================================
    // Internals - customer cart
    // =================================================================

    /**
     * @param array<string,mixed> $snapshot
     */
    private function addToCustomerCart(int $customerId, array $snapshot, int $qty): void
    {
        $cart = $this->carts->findOrCreateForCustomer($customerId);

        $existing = $this->items->findForCart(
            $cart->id,
            (int) $snapshot['product_id'],
            (int) $snapshot['variant_id']
        );

        if ($existing !== null) {
            $newQty = $existing->quantity + $qty;
            $this->assertStockAvailable(
                $existing->productId,
                $existing->variantId,
                $newQty
            );
            $this->items->incrementQuantity(
                $cart->id,
                $existing->productId,
                $existing->variantId,
                $qty
            );
            return;
        }

        $item = new CartItem();
        $item->cartId       = $cart->id;
        $item->productId    = (int) $snapshot['product_id'];
        $item->variantId    = (int) $snapshot['variant_id'];
        $item->quantity     = $qty;
        $item->unitPrice    = (float) $snapshot['unit_price'];
        $item->productName  = (string) $snapshot['product_name'];
        $item->variantLabel = $snapshot['variant_label'];
        $item->sku          = $snapshot['sku'];

        $this->items->addItem($cart->id, $item);
    }

    // =================================================================
    // Internals - session cart
    // =================================================================

    /**
     * @param array<string,mixed> $snapshot
     */
    private function addToSessionCart(array $snapshot, int $qty): void
    {
        $cart = $this->session->get(self::SESSION_CART_KEY, []);
        if (!is_array($cart) || !isset($cart['items']) || !is_array($cart['items'])) {
            $cart = ['items' => []];
        }

        $productId = (int) $snapshot['product_id'];
        $variantId = (int) $snapshot['variant_id'];

        // Look for an existing item with the same composite key.
        foreach ($cart['items'] as $i => $existing) {
            if ((int) $existing['product_id'] === $productId
                && (int) $existing['variant_id'] === $variantId) {
                $newQty = (int) $existing['quantity'] + $qty;
                $this->assertStockAvailable($productId, $variantId, $newQty);
                $cart['items'][$i]['quantity'] = $newQty;
                $cart['updated_at'] = time();
                $this->session->set(self::SESSION_CART_KEY, $cart);
                return;
            }
        }

        $cart['items'][] = [
            'product_id'    => $productId,
            'variant_id'    => $variantId,
            'quantity'      => $qty,
            'unit_price'    => (float) $snapshot['unit_price'],
            'product_name'  => (string) $snapshot['product_name'],
            'variant_label' => $snapshot['variant_label'],
            'sku'           => $snapshot['sku'],
            'added_at'      => time(),
        ];
        $cart['updated_at'] = time();
        $this->session->set(self::SESSION_CART_KEY, $cart);
    }

    private function updateSessionItemQuantity(int $productId, int $variantId, int $qty): void
    {
        $cart = $this->session->get(self::SESSION_CART_KEY, []);
        if (!is_array($cart) || !isset($cart['items']) || !is_array($cart['items'])) {
            return;
        }
        foreach ($cart['items'] as $i => $existing) {
            if ((int) $existing['product_id'] === $productId
                && (int) $existing['variant_id'] === $variantId) {
                $cart['items'][$i]['quantity'] = $qty;
                $cart['updated_at'] = time();
                $this->session->set(self::SESSION_CART_KEY, $cart);
                return;
            }
        }
    }

    private function removeSessionItem(int $productId, int $variantId): void
    {
        $cart = $this->session->get(self::SESSION_CART_KEY, []);
        if (!is_array($cart) || !isset($cart['items']) || !is_array($cart['items'])) {
            return;
        }
        $kept = [];
        foreach ($cart['items'] as $existing) {
            if ((int) $existing['product_id'] === $productId
                && (int) $existing['variant_id'] === $variantId) {
                continue;
            }
            $kept[] = $existing;
        }
        $cart['items'] = array_values($kept);
        $cart['updated_at'] = time();
        $this->session->set(self::SESSION_CART_KEY, $cart);
    }

    private function sessionCount(): int
    {
        $items = $this->sessionItems();
        $total = 0;
        foreach ($items as $item) {
            $total += (int) $item['quantity'];
        }
        return $total;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function sessionItems(): array
    {
        $cart = $this->session->get(self::SESSION_CART_KEY, []);
        if (!is_array($cart) || !isset($cart['items']) || !is_array($cart['items'])) {
            return [];
        }
        return $cart['items'];
    }

    // =================================================================
    // Internals - shared
    // =================================================================

    /**
     * Read the raw cart items, regardless of storage. Returns
     * CartItem models in both cases.
     *
     * @return array<int, CartItem>
     */
    private function rawItems(): array
    {
        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            $cart = $this->carts->findByCustomer($customer->id);
            if ($cart === null) {
                return [];
            }
            return $this->items->listForCart($cart->id);
        }

        $sessionItems = $this->sessionItems();
        $out = [];
        foreach ($sessionItems as $s) {
            $out[] = CartItem::fromSessionItem(0, $s);
        }
        return $out;
    }

    /**
     * Build a snapshot of the current product/variant state.
     * Validates the product exists, is published, and (if the
     * caller requests stock checks separately) that stock is
     * available for the requested quantity.
     *
     * @return array<string,mixed>
     * @throws CartException
     */
    private function buildSnapshot(int $productId, int $variantId, int $qty): array
    {
        $product = $this->products->findById($productId);
        if ($product === null) {
            throw new CartException('Product not found.');
        }
        if ($product->status !== 'published') {
            throw new CartException('This product is not available.');
        }

        $variantLabel = null;
        $sku          = $product->sku;

        if ($variantId > 0) {
            $variant = $this->variants->findById($variantId);
            if ($variant === null || $variant->productId !== $productId) {
                throw new CartException('This product option is not available.');
            }
            $variantLabel = $variant->attributeSummary !== ''
                ? $variant->attributeSummary
                : null;
            if ($variant->sku !== null && $variant->sku !== '') {
                $sku = $variant->sku;
            }
        }

        $this->assertStockAvailable($productId, $variantId, $qty);

        return [
            'product_id'    => $productId,
            'variant_id'    => $variantId,
            'unit_price'    => $product->price,
            'product_name'  => $product->name,
            'variant_label' => $variantLabel,
            'sku'           => $sku,
        ];
    }

    /**
     * @throws CartException if the requested quantity cannot be sold
     */
    private function assertStockAvailable(int $productId, int $variantId, int $qty): void
    {
        $inv = $this->inventory->forUnit($productId, $variantId);

        // No inventory row: the product does not track stock.
        // Treat as always available.
        if ($inv === null) {
            return;
        }

        if (!$inv->canSell($qty)) {
            if ($inv->backordersAllowed) {
                return;
            }
            throw new CartException('Not enough stock available.');
        }
    }
}
