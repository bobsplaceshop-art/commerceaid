<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

use CorporateAid\Core\Container;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Services\CartService;

/**
 * Base controller for every storefront page.
 *
 * Mirrors AdminController in shape. The difference is the layout:
 * storefront pages render into layouts.storefront (public shell)
 * instead of layouts.app (admin shell). Same View class, same
 * render pipeline, different shell.
 *
 * Context injected by render():
 *   - $store    - the resolved store, or null
 *   - $title    - page title (for <title> and breadcrumbs)
 *
 * Storefront controllers do NOT receive:
 *   - $user         - customers are not modeled here; individual
 *                     controllers that need the customer resolve
 *                     it from CustomerAuth
 *   - $setupComplete - that is an admin-only concern for gating
 *                      sidebar items
 *
 * See docs/stages/008_storefront-plan.md for the full design.
 */
abstract class StorefrontController
{
    public function __construct(protected Container $container)
    {
    }

    /**
     * Render a view inside the storefront shell.
     *
     * @param string $view  dot-notation view name, e.g.
     *                      'storefront.home'
     * @param array<string,mixed> $data  view-specific data
     */
    protected function render(string $view, array $data = []): Response
    {
        $viewSvc = $this->container->get(View::class);

        $data['store']     = $this->resolveStore();
        $data['title']     = $data['title'] ?? '';
        $data['cartCount'] = $this->resolveCartCount();

        return Response::html(
            $viewSvc->render($view, $data, 'storefront.layouts.main')
        );
    }

    /**
     * Cart unit count for the header badge. Resolved once per
     * request so views and the layout share the same value.
     *
     * Defensive: if the cart service fails for any reason, the
     * page still renders with a count of 0. A broken cart should
     * not break the whole storefront.
     */
    private function resolveCartCount(): int
    {
        try {
            return $this->container->get(CartService::class)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * The current store, or null if the store context hasn't been
     * populated. Called inside render() so every view receives it.
     */
    private function resolveStore(): ?\CorporateAid\Commerce\Models\Store
    {
        $context = $this->container->get(StoreContext::class);
        if (!$context->has()) {
            return null;
        }

        try {
            $repo = $this->container->get(
                \CorporateAid\Commerce\Repositories\StoreRepository::class
            );
            return $repo->findById($context->id());
        } catch (\Throwable) {
            return null;
        }
    }
}
