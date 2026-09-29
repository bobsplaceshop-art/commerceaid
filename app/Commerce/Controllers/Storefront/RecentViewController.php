<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Services\RecentViewService;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Storefront recently-viewed.
 *
 * Two endpoints:
 *
 *   GET  /account/recent        index   account page
 *   POST /account/recent/clear  clear   form POST only
 *
 * No AJAX. Recording a view is done inside
 * ProductController::show(), not via a route. Clearing the
 * history is a one-click action, no partial update needed.
 * There is no toggle, no per-row action, no live-updating
 * badge.
 *
 * The index page requires a logged-in customer. Guests
 * accumulate session history that merges on login, but they
 * do not see /account/recent until they log in - same gate as
 * /account/wishlist.
 *
 * See docs/stages/008g.2_recently-viewed-plan.md.
 */
final class RecentViewController extends StorefrontController
{
    // =================================================================
    // Show
    // =================================================================

    /**
     * The /account/recent page.
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

        $recent   = $this->container->get(RecentViewService::class);
        $products = $this->container->get(ProductRepository::class);
        $session  = $this->container->get(Session::class);

        // Pair each RecentView with a live Product. Archived or
        // deleted products are dropped from the page with a
        // per-row "no longer available" state, not filtered.
        $rows = [];
        foreach ($recent->items() as $rv) {
            $product = $products->findById($rv->productId);
            $rows[] = [
                'view'    => $rv,
                'product' => $product,
            ];
        }

        return $this->renderAccount('storefront.account.recent', [
            'title'    => 'Recently Viewed',
            'customer' => $customer,
            'rows'     => $rows,
            'count'    => $recent->count(),
            'flashOk'  => $session->getFlash('recent_success'),
        ]);
    }

    // =================================================================
    // Clear
    // =================================================================

    /**
     * Clear the customer's entire recently-viewed history.
     * Form POST only.
     *
     * @param array<string,string> $params
     */
    public function clear(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $customer = $customerAuth->user();
        if ($customer === null) {
            return Response::redirect('/account/login');
        }

        $count = $this->container->get(RecentViewService::class)->clear();

        $session = $this->container->get(Session::class);
        $session->flash(
            'recent_success',
            $count === 0
                ? 'Your history was already empty.'
                : 'Cleared ' . $count . ' ' . ($count === 1 ? 'item' : 'items') . ' from your history.'
        );

        return Response::redirect('/account/recent');
    }

    // =================================================================
    // Internals
    // =================================================================

    /**
     * Render a page inside the account shell.
     *
     * The shell view (storefront.account._shell) reads
     * $_account_inner_view and delegates to it. Same pattern
     * used by AccountController and WishlistController.
     *
     * @param array<string,mixed> $data
     */
    private function renderAccount(string $view, array $data): Response
    {
        $data['_account_inner_view'] = $view;
        return parent::render('storefront.account._shell', $data);
    }
}
