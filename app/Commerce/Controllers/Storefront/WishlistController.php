<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Services\WishlistService;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Storefront wishlist.
 *
 * Works for both guests and logged-in customers. WishlistService
 * resolves the storage layer internally; the controller doesn't
 * care which one it is.
 *
 * Response shape mirrors CartController exactly:
 *
 *   Accept: application/json  -> JSON response
 *   anything else             -> flash + redirect
 *
 * The heart button on product cards is always AJAX (JSON). The
 * wishlist page's own remove links fall back to form POST for
 * progressive enhancement.
 *
 * The /account/wishlist page itself requires a logged-in
 * customer. Guests who click the heart on a product card
 * accumulate a session wishlist that merges on login. They do
 * not see the /account/wishlist page until after they log in.
 *
 * No validation errors surface to the customer. A wishlist
 * add/remove either succeeds or is idempotently a no-op. There
 * is nothing to reject.
 *
 * See docs/stages/008g.1_wishlist-plan.md.
 */
final class WishlistController extends StorefrontController
{
    // =================================================================
    // Show
    // =================================================================

    /**
     * The /account/wishlist page. Requires login. Renders inside
     * the account shell (see AccountController for the shell
     * pattern).
     *
     * @param array<string,string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer = $customerAuth->user();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $wishlist = $this->container->get(WishlistService::class);

        // Resolve each wishlist row into a live Product. Archived
        // or deleted products are dropped from the page with a
        // per-row status, not a hard filter, so the customer can
        // see what disappeared. The repository returns
        // WishlistItem rows; the controller pairs them with
        // Product lookups.
        $wishlistItems = $wishlist->items();
        $productRepo = $this->container->get(
            \CorporateAid\Commerce\Repositories\ProductRepository::class
        );

        $rows = [];
        foreach ($wishlistItems as $w) {
            $product = $productRepo->findById($w->productId);
            $rows[] = [
                'wishlist' => $w,
                'product'  => $product,
            ];
        }

        return $this->renderAccount('storefront.account.wishlist', [
            'title'    => 'My Wishlist',
            'customer' => $customer,
            'rows'     => $rows,
            'count'    => $wishlist->count(),
        ]);
    }

    // =================================================================
    // Mutations
    // =================================================================

    /**
     * Add a product. Accepts form POST (redirect) and AJAX POST
     * (JSON). Idempotent.
     *
     * @param array<string,string> $params
     */
    public function add(Request $request, array $params = []): Response
    {
        $productId = (int) $request->input('product_id', 0);
        $wishlist  = $this->container->get(WishlistService::class);

        $added = $wishlist->add($productId);

        return $this->respondToWishlistAction(
            $request,
            true,
            $added ? 'Added to wishlist.' : 'Already on your wishlist.',
            $productId,
            true
        );
    }

    /**
     * Remove a product. Accepts form POST (redirect) and AJAX
     * POST (JSON). Idempotent.
     *
     * @param array<string,string> $params
     */
    public function remove(Request $request, array $params = []): Response
    {
        $productId = (int) $request->input('product_id', 0);
        $wishlist  = $this->container->get(WishlistService::class);

        $removed = $wishlist->remove($productId);

        return $this->respondToWishlistAction(
            $request,
            true,
            $removed ? 'Removed from wishlist.' : 'Not on your wishlist.',
            $productId,
            false
        );
    }

    /**
     * Toggle a product. JSON only. Used by the heart button,
     * which always runs as AJAX.
     *
     * Returns the new state so the JS can update the icon
     * without a second round-trip.
     *
     * @param array<string,string> $params
     */
    public function toggle(Request $request, array $params = []): Response
    {
        $productId = (int) $request->input('product_id', 0);
        $wishlist  = $this->container->get(WishlistService::class);

        $isOn = $wishlist->toggle($productId);

        return Response::json([
            'ok'        => true,
            'on'        => $isOn,
            'productId' => $productId,
            'count'     => $wishlist->count(),
        ]);
    }

    /**
     * Current wishlist product IDs. JSON only. Used by the
     * storefront JS to resync heart state after navigation if
     * the server-rendered state is stale.
     *
     * @param array<string,string> $params
     */
    public function ids(Request $request, array $params = []): Response
    {
        $wishlist = $this->container->get(WishlistService::class);

        return Response::json([
            'ids'   => $wishlist->productIds(),
            'count' => $wishlist->count(),
        ]);
    }

    // =================================================================
    // Response shaping
    // =================================================================

    /**
     * Emit the response for a wishlist mutation. Form POST gets
     * a flash + redirect back. AJAX POST gets JSON.
     *
     * @param string $message customer-facing message
     */
    private function respondToWishlistAction(
        Request $request,
        bool $ok,
        string $message,
        int $productId,
        bool $nowOn
    ): Response {
        if ($this->wantsJson($request)) {
            $wishlist = $this->container->get(WishlistService::class);
            return Response::json([
                'ok'        => $ok,
                'message'   => $message,
                'on'        => $nowOn,
                'productId' => $productId,
                'count'     => $wishlist->count(),
            ]);
        }

        $session = $this->container->get(Session::class);
        $session->flash($ok ? 'success' : 'error', $message);

        return Response::redirect($this->safeBackUrl($request));
    }

    private function wantsJson(Request $request): bool
    {
        $accept = (string) $request->header('Accept', '');
        return str_contains($accept, 'application/json');
    }

    /**
     * The URL to redirect to after a form POST. Uses the Referer
     * header if it's a same-origin path, falls back to
     * /account/wishlist.
     *
     * Mirrors CartController::safeBackUrl.
     */
    private function safeBackUrl(Request $request): string
    {
        $referer = (string) $request->header('Referer', '');
        if ($referer === '') {
            return '/account/wishlist';
        }

        $path = parse_url($referer, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path[0] !== '/') {
            return '/account/wishlist';
        }
        if (str_starts_with($path, '//')) {
            return '/account/wishlist';
        }

        return $path;
    }

    /**
     * Render a page inside the account shell.
     *
     * The shell view (storefront.account._shell) reads
     * $_account_inner_view and delegates to it. Same pattern
     * AccountController uses.
     *
     * @param array<string,mixed> $data
     */
    private function renderAccount(string $view, array $data): Response
    {
        $data['_account_inner_view'] = $view;
        return parent::render('storefront.account._shell', $data);
    }
}
