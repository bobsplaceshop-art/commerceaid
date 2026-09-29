<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\RecentView;
use CorporateAid\Commerce\Repositories\RecentViewRepository;
use CorporateAid\Core\Session;

/**
 * Recently-viewed service.
 *
 * Same session-vs-DB split as the cart and wishlist:
 *
 *   Guest     -> $_SESSION['recently_viewed'] - a flat array
 *                of product IDs, newest first, capped at 50.
 *   Customer  -> commerce_recently_viewed table.
 *
 * The guest session shape differs from the wishlist in one
 * meaningful way: order carries meaning. Re-viewing an
 * existing product moves it to the front of the session
 * array, mirroring what the DB does with its viewed_at
 * column. The wishlist is a set; the recently-viewed list is
 * a queue.
 *
 * record() is called from ProductController::show() on every
 * product detail page load. It is a side effect of viewing,
 * not an explicit customer action. The call site wraps it in
 * try/catch so a recording failure never breaks the page.
 *
 * mergeGuestHistoryOnLogin() is called from the same two
 * places mergeGuestCartOnLogin() and mergeGuestWishlistOnLogin()
 * are called - in AuthController after the customer ID is
 * written to the session.
 *
 * See docs/stages/008g.2_recently-viewed-plan.md.
 */
final class RecentViewService
{
    private const SESSION_KEY = 'recently_viewed';

    public function __construct(
        private Session $session,
        private RecentViewRepository $recent,
        private CustomerAuthService $customerAuth,
    ) {
    }

    // =================================================================
    // Mutations
    // =================================================================

    /**
     * Record a product view.
     *
     * For guests: update the session array (unshift, dedup,
     * cap). Idempotent - a re-view moves the product to the
     * front rather than duplicating.
     *
     * For customers: upsert the DB row. The schema's
     * ON UPDATE CURRENT_TIMESTAMP bumps viewed_at, which moves
     * the product to the top of the ordering.
     *
     * This method never throws. It swallows all failures. A
     * view that cannot be recorded is not worth surfacing to
     * the customer.
     */
    public function record(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        try {
            $customer = $this->customerAuth->user();
            if ($customer !== null) {
                $this->recent->record($customer->id, $productId);
                return;
            }

            $this->addToSession($productId);
        } catch (\Throwable) {
            // Swallow. Recording a view is a best-effort side
            // effect. The page rendering is what matters.
        }
    }

    /**
     * Clear the customer's entire history.
     *
     * For guests: empty the session array.
     * For customers: DELETE all rows.
     *
     * Returns the number of items cleared (rows deleted for
     * customers, array length for guests). Used to build a
     * flash message on the account page.
     */
    public function clear(): int
    {
        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            return $this->recent->clearFor($customer->id);
        }

        $count = count($this->sessionProductIds());
        $this->session->forget(self::SESSION_KEY);
        return $count;
    }

    // =================================================================
    // Reads
    // =================================================================

    /**
     * The customer's history as RecentView models, for the
     * /account/recent page. Guests get an empty array - they
     * have to log in to see a persisted list.
     *
     * @return array<int, RecentView>
     */
    public function items(): array
    {
        $customer = $this->customerAuth->user();
        if ($customer === null) {
            return [];
        }
        return $this->recent->listFor($customer->id);
    }

    /**
     * The full list of recently-viewed product IDs for the
     * current viewer, newest first. Used by the
     * recently_viewed_product_ids() helper.
     *
     * @return array<int, int>
     */
    public function productIds(): array
    {
        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            return $this->recent->productIdsFor($customer->id);
        }
        return $this->sessionProductIds();
    }

    /**
     * Number of items in the history. Capped at 50 for
     * customers (by the repository's trim) and for guests (by
     * addToSession).
     */
    public function count(): int
    {
        $customer = $this->customerAuth->user();
        if ($customer !== null) {
            return $this->recent->countFor($customer->id);
        }
        return count($this->sessionProductIds());
    }

    // =================================================================
    // Merge on login
    // =================================================================

    /**
     * Merge the guest session history into the customer's DB
     * history. Called from AuthController in the same two
     * places mergeGuestCartOnLogin() and
     * mergeGuestWishlistOnLogin() are called.
     *
     * The session array is newest-first. The repository's
     * bulkAdd() handles the ordering-preservation detail.
     *
     * The session key is cleared after merge.
     */
    public function mergeGuestHistoryOnLogin(): void
    {
        $sessionIds = $this->sessionProductIds();
        if ($sessionIds === []) {
            return;
        }

        $customer = $this->customerAuth->user();
        if ($customer === null) {
            return;
        }

        $this->recent->bulkAdd($customer->id, $sessionIds);
        $this->session->forget(self::SESSION_KEY);
    }

    // =================================================================
    // Internals - session history
    // =================================================================

    /**
     * Read the session array, filter to positive ints, dedup,
     * preserve order. Never throws.
     *
     * @return array<int, int>
     */
    private function sessionProductIds(): array
    {
        $raw = $this->session->get(self::SESSION_KEY, []);
        if (!is_array($raw)) {
            return [];
        }

        $seen = [];
        $out  = [];
        foreach ($raw as $v) {
            $v = (int) $v;
            if ($v <= 0 || isset($seen[$v])) {
                continue;
            }
            $seen[$v] = true;
            $out[] = $v;
        }

        // Cap defensively. The session array should already be
        // capped by addToSession, but a manually-edited or
        // corrupt session could be larger.
        $cap = RecentViewRepository::MAX_ITEMS;
        if (count($out) > $cap) {
            $out = array_slice($out, 0, $cap);
        }

        return $out;
    }

    /**
     * Add a product to the guest session history. Moves an
     * existing entry to the front. Caps at MAX_ITEMS.
     *
     * The array is newest-first. Position 0 is the most
     * recently viewed.
     */
    private function addToSession(int $productId): void
    {
        $ids = $this->sessionProductIds();

        // Remove if present. array_values reindexes.
        $idx = array_search($productId, $ids, true);
        if ($idx !== false) {
            unset($ids[$idx]);
            $ids = array_values($ids);
        }

        // Prepend.
        array_unshift($ids, $productId);

        // Cap.
        $cap = RecentViewRepository::MAX_ITEMS;
        if (count($ids) > $cap) {
            $ids = array_slice($ids, 0, $cap);
        }

        $this->session->set(self::SESSION_KEY, $ids);
    }
}
