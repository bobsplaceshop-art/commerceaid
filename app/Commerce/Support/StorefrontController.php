<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

use CorporateAid\Core\Container;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Services\CartService;
use CorporateAid\Commerce\Services\WishlistService;

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
        $data['wishlistProductIds']  = $this->resolveWishlistProductIds();
        $data['cookieBannerEnabled'] = $this->resolveCookieBannerEnabled();
        $data['navCategories']       = $this->resolveNavCategories();
        $data['storeNotice']         = $this->resolveStoreNotice();

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
     * Filter flag for the storefront catalog: hide products
     * whose every sellable unit is out of stock.
     *
     * Read once per request. Returns ['hide_out_of_stock' => true]
     * when the setting is on, an empty array otherwise so it
     * can be merged into any filter array.
     *
     * @return array<string,mixed>
     */
    protected function hideOutOfStockFilter(): array
    {
        try {
            $settings = $this->container->get(
                \CorporateAid\Commerce\Repositories\StoreSettingsRepository::class
            );
            $enabled = $settings->bool('catalog.hide_out_of_stock', false);
            return $enabled ? ['hide_out_of_stock' => true] : [];
        } catch (\Throwable $e) {
            return [];
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
     * Wishlist product IDs for the current viewer. Used to pre-
     * fill the heart state on every product card without an
     * AJAX round-trip. Guests get their session wishlist;
     * logged-in customers get their DB wishlist.
     *
     * Defensive: if the service fails for any reason, the array
     * is empty. A broken wishlist should not break the storefront.
     *
     * @return array<int, int>
     */
    private function resolveWishlistProductIds(): array
    {
        try {
            return $this->container->get(WishlistService::class)->productIds();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The active categories for the storefront header's
     * navigation. Resolved once per request.
     *
     * Defensive: returns an empty array on failure, so
     * a broken category query does not break the whole
     * storefront header.
     *
     * @return array<int, \CorporateAid\Commerce\Models\Category>
     */
    private function resolveNavCategories(): array
    {
        try {
            $repo = $this->container->get(
                \CorporateAid\Commerce\Repositories\CategoryRepository::class
            );
            return $repo->listAll(false);
        } catch (\Throwable $e) {
            return [];
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

    /**
     * The storefront banner notice. Reads the merchant's
     * custom notice first; if empty, checks the scheduled
     * update time and produces a "Scheduled maintenance"
     * notice if the time is within the next 24 hours.
     *
     * The banner renders at the top of every storefront
     * page. It is a read-only informational surface - it
     * does NOT lock the site. The site stays usable until
     * the scheduled time arrives, at which point a separate
     * maintenance gate (Stage 017 Session 3) takes over.
     *
     * Defensive: returns '' on any failure, so a broken
     * settings table cannot break the storefront.
     */
    private function resolveStoreNotice(): string
    {
        try {
            $settings = $this->container->get(
                \CorporateAid\Commerce\Repositories\StoreSettingsRepository::class
            );

            $custom = trim($settings->string('general.store_notice', ''));
            if ($custom !== '') {
                return $custom;
            }

            $scheduled = trim($settings->string('system.update.scheduled_at', ''));
            if ($scheduled === '') {
                return '';
            }

            $ts = strtotime($scheduled);
            if ($ts === false) {
                return '';
            }

            $now = time();
            if ($ts <= $now || ($ts - $now) > 86400) {
                return '';
            }

            return 'Scheduled maintenance on ' . date('M j \a\t H:i', $ts)
                . '. The store may be briefly unavailable.';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
