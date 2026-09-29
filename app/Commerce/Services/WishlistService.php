<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\WishlistItem;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\WishlistRepository;
use CorporateAid\Core\Session;

/**
 * Wishlist service.
 *
 * Like the cart, the wishlist is stateful and lives in one of
 * two places depending on whether a customer is logged in:
 *
 *   Guest     -> $_SESSION['wishlist'] - a flat array of
 *                product IDs. No variants, no quantities, no
 *                snapshots. The heart toggles membership in
 *                the array.
 *   Customer  -> commerce_wishlists table, keyed by
 *                (store_id, customer_id, product_id).
 *
 * Every public method resolves the storage location
 * internally. The caller does not need to know whether it is
 * dealing with a guest or a customer.
 *
 * mergeGuestWishlistOnLogin() is called from the same two
 * places mergeGuestCartOnLogin() is called - in
 * AuthController, right after the customer id is written to
 * the session. It moves any IDs in the session wishlist into
 * the customer's DB wishlist and clears the session key.
 *
 * No stock check, no price lookup, no product validation on
 * add. A wishlist is a bookmark. The product might be sold out
 * or even archived when the customer comes back; the wishlist
 * page surfaces that as a per-row warning, but the wishlist
 * itself accepts any product_id the customer hearts.
 *
 * See docs/stages/008g.1_wishlist-plan.md.
 */
final class WishlistService
{
    private const SESSION_WISHLIST_KEY = 'wishlist';

    public function __construct(
        private Session $session,
        private WishlistRepository $wishlist,
        private ProductRepository $products,
        private CustomerAuthService $customerAuth,
    ) {
    }

    // =================================================================
    // Mutations
    // =================================================================

    /**
     * Toggle membership. Returns true if the product is now on
     * the wishlist, false if it was removed. Used by the heart
     * button, which does not care whether the click was an add
     * or a remove - it just needs the new state.
     */
    public function toggle(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        if ($this->has($productId)) {
            $this->remove($productId);
            return false;
        }

        $this->add($productId);
        return true;
    }

    /**
     * Add a product. Idempotent: adding twice is a no-op.
     * Returns true if the product was newly added, false if it
     * was already on the wishlist.
     */
    public function add(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            return $this->wishlist->add($customer->id, $productId);
        }

        return $this->addToSession($productId);
    }

    /**
     * Remove a product. Idempotent: removing an absent product
     * is a no-op. Returns true if the product was removed,
     * false if it was not on the wishlist.
     */
    public function remove(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            return $this->wishlist->remove($customer->id, $productId);
        }

        return $this->removeFromSession($productId);
    }

    // =================================================================
    // Reads
    // =================================================================

    /**
     * Whether the current viewer has this product wishlisted.
     * Used by the product detail page and by the wishlist page
     * itself.
     */
    public function has(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            return $this->wishlist->exists($customer->id, $productId);
        }

        return in_array($productId, $this->sessionProductIds(), true);
    }

    /**
     * The full list of wishlisted product IDs for the current
     * viewer. Used by StorefrontController::render() to inject
     * $wishlistProductIds into every storefront view, so
     * product cards can pre-fill their heart state.
     *
     * @return array<int, int>
     */
    public function productIds(): array
    {
        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            return $this->wishlist->productIdsFor($customer->id);
        }

        return $this->sessionProductIds();
    }

    /**
     * The customer's wishlist as WishlistItem models, for the
     * /account/wishlist page. Guests get an empty array - they
     * have to log in to see a persisted list.
     *
     * @return array<int, WishlistItem>
     */
    public function items(): array
    {
        $customer = $this->customerAuth->user();
        if ($customer === null) {
            return [];
        }
        return $this->wishlist->listFor($customer->id);
    }

    /**
     * Number of items on the wishlist. Used by the account
     * sidebar badge.
     */
    public function count(): int
    {
        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            return $this->wishlist->countFor($customer->id);
        }
        return count($this->sessionProductIds());
    }

    // =================================================================
    // Merge on login
    // =================================================================

    /**
     * Merge the guest session wishlist into the customer's DB
     * wishlist. Called from AuthController in the same two
     * places mergeGuestCartOnLogin() is called.
     *
     * Conflict rule: existing rows are kept. New IDs are added.
     * No "last write wins" - a wishlist entry is either there
     * or not, and the two sides union.
     *
     * The session wishlist is cleared after merge.
     */
    public function mergeGuestWishlistOnLogin(): void
    {
        $sessionIds = $this->sessionProductIds();
        if ($sessionIds === []) {
            return;
        }

        $customer = $this->customerAuth->user();
        if ($customer === null) {
            return;
        }

        $this->wishlist->bulkAdd($customer->id, $sessionIds);
        $this->session->forget(self::SESSION_WISHLIST_KEY);
    }

    // =================================================================
    // Internals - session wishlist
    // =================================================================

    /**
     * @return array<int, int>
     */
    private function sessionProductIds(): array
    {
        $raw = $this->session->get(self::SESSION_WISHLIST_KEY, []);
        if (!is_array($raw)) {
            return [];
        }
        // Filter to positive ints and deduplicate.
        $ids = [];
        foreach ($raw as $v) {
            $v = (int) $v;
            if ($v > 0) {
                $ids[$v] = true;
            }
        }
        return array_keys($ids);
    }

    private function addToSession(int $productId): bool
    {
        $ids = $this->sessionProductIds();
        if (in_array($productId, $ids, true)) {
            return false;
        }
        $ids[] = $productId;
        $this->session->set(self::SESSION_WISHLIST_KEY, $ids);
        return true;
    }

    private function removeFromSession(int $productId): bool
    {
        $ids = $this->sessionProductIds();
        $idx = array_search($productId, $ids, true);
        if ($idx === false) {
            return false;
        }
        unset($ids[$idx]);
        $this->session->set(self::SESSION_WISHLIST_KEY, array_values($ids));
        return true;
    }
}
