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

        $data['store']               = $this->resolveStore();
        $data['title']               = $data['title'] ?? '';
        $data['cartCount']           = $this->resolveCartCount();
        $data['cookieBannerEnabled'] = $this->resolveCookieBannerEnabled();

        return Response::html(
            $viewSvc->render($view, $data, 'storefront.layouts.main')
        );
    }

    /**
     * Whether the cookie consent banner should render on this
     * page load. Reads the store setting once per request.
     *
     * The layout renders the banner partial, its CSS, and its JS
     * only when this returns true. The CSS and JS are not loaded
     * at all when the banner is disabled - no dead assets.
     *
     * Defensive: if the settings read fails for any reason, the
     * banner is disabled. A broken settings table should not
     * break the storefront.
     */
    private function resolveCookieBannerEnabled(): bool
    {
        try {
            $settings = $this->container->get(
                \CorporateAid\Commerce\Repositories\StoreSettingsRepository::class
            );
            // Default true: German deployment requires the banner.
            // The row is absent on a fresh store until someone
            // visits Store Settings and toggles it.
            return $settings->bool('compliance.cookie_banner.enabled', true);
        } catch (\Throwable $e) {
            return false;
        }
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
