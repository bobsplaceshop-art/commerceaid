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
 * storefront pages render into the active theme's layout
 * (themes/{slug}/layouts/main) instead of the admin shell.
 * Same View class, same render pipeline, different shell.
 *
 * Context injected by render():
 *   - $store    - the resolved store, or null
 *   - $title    - page title (for <title> and breadcrumbs)
 *
 * See docs/stages/008_storefront-plan.md for the full design.
 */
abstract class StorefrontController
{
    public function __construct(protected Container $container)
    {
    }

    /**
     * Render a view inside the active theme's layout.
     *
     * @param string $view  dot-notation view name, e.g.
     *                      'storefront.home'
     * @param array<string,mixed> $data  view-specific data
     */
    protected function render(string $view, array $data = []): Response
    {
        $viewSvc = $this->container->get(View::class);
        $store   = $this->resolveStore();

        $data['store']               = $store;
        $data['title']               = $data['title'] ?? '';
        $data['cartCount']           = $this->resolveCartCount();
        $data['wishlistProductIds']  = $this->resolveWishlistProductIds();
        $data['cookieBannerEnabled'] = $this->resolveCookieBannerEnabled();
        $data['popupEnabled']        = $this->resolvePopupEnabled();
        $data['navCategories']       = $this->resolveNavCategories();
        $data['storeNotice']         = $this->resolveStoreNotice();

        // Resolve the active theme's layout. Falls back to 'modern'
        // if the store's chosen theme doesn't exist (broken install,
        // removed folder, or unset column).
        $themeSlug  = $store !== null ? $store->themeSlug : 'modern';
        $layoutPath = 'themes.' . $themeSlug . '.layouts.main';

        try {
            $registry = $this->container->get(\CorporateAid\Commerce\Services\Theme\ThemeRegistry::class);
            if (!$registry->exists($themeSlug)) {
                $themeSlug  = 'modern';
                $layoutPath = 'themes.modern.layouts.main';
            }
        } catch (\Throwable) {
            $themeSlug  = 'modern';
            $layoutPath = 'themes.modern.layouts.main';
        }

        // Tell View which theme is active so view_partial() calls
        // for storefront/partials/header|footer|utility-bar resolve
        // the theme's own version first.
        $viewSvc->setActiveTheme($themeSlug);

        return Response::html(
            $viewSvc->render($view, $data, $layoutPath)
        );
    }

    /**
     * Build a map of product_id => ['avg' => float, 'count' => int]
     * for the given products. Used by _product-card.php to render
     * star ratings. Products without any approved reviews are
     * omitted, so the card's rating block hides itself.
     *
     * @param array<int, \CorporateAid\Commerce\Models\Product> $products
     * @return array<int, array{avg: float, count: int}>
     */
    protected function ratingsForProducts(array $products): array
    {
        if ($products === []) {
            return [];
        }

        $reviews = $this->container->get(
            \CorporateAid\Commerce\Repositories\ProductReviewRepository::class
        );

        $out = [];
        foreach ($products as $product) {
            $id = (int) $product->id;
            $count = $reviews->countApprovedForProduct($id);
            if ($count === 0) {
                continue;
            }
            $out[$id] = [
                'avg'   => (float) ($reviews->averageForProduct($id) ?? 0.0),
                'count' => $count,
            ];
        }

        return $out;
    }

    /**
     * The storefront banner notice. Reads the merchant's custom
     * notice first; if empty, checks the scheduled update time
     * and produces a "Scheduled maintenance" notice if the time
     * is within the next 24 hours.
     *
     * Defensive: returns '' on any failure.
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
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Whether the cookie consent banner should render.
     * Defensive: returns false on any failure.
     */
    private function resolveCookieBannerEnabled(): bool
    {
        try {
            $settings = $this->container->get(
                \CorporateAid\Commerce\Repositories\StoreSettingsRepository::class
            );
            return $settings->bool('compliance.cookie_banner.enabled', true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether the popup banner should render on this request.
     *
     * True only when:
     *   - popup.enabled is set
     *   - popup.headline is non-empty
     *   - the current date is within popup.starts_at / popup.ends_at
     *     (blank bounds are unbounded)
     *
     * The cookie-based dismissal is handled client-side.
     * This method only gates server-side rendering.
     */
    private function resolvePopupEnabled(): bool
    {
        try {
            $settings = $this->container->get(
                \CorporateAid\Commerce\Repositories\StoreSettingsRepository::class
            );

            if (!$settings->bool('popup.enabled', false)) {
                return false;
            }

            $headline = trim($settings->string('popup.headline', ''));
            if ($headline === '') {
                return false;
            }

            $startsAt = trim($settings->string('popup.starts_at', ''));
            $endsAt   = trim($settings->string('popup.ends_at', ''));
            $now      = new \DateTimeImmutable('now');

            if ($startsAt !== '') {
                $start = \DateTimeImmutable::createFromFormat('Y-m-d', $startsAt);
                if ($start === false || $now < $start) {
                    return false;
                }
            }

            if ($endsAt !== '') {
                $end = \DateTimeImmutable::createFromFormat('Y-m-d', $endsAt);
                if ($end === false) {
                    return false;
                }
                $end = $end->modify('+1 day');
                if ($now >= $end) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Filter flag for the storefront catalog: hide products
     * whose every sellable unit is out of stock.
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
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Cart unit count for the header badge.
     * Defensive: returns 0 on failure.
     */
    private function resolveCartCount(): int
    {
        try {
            return $this->container->get(CartService::class)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Wishlist product IDs for the current viewer.
     * Defensive: returns [] on failure.
     *
     * @return array<int,int>
     */
    private function resolveWishlistProductIds(): array
    {
        try {
            return $this->container->get(WishlistService::class)->productIds();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Active categories for the storefront header navigation.
     * Defensive: returns [] on failure.
     *
     * @return array<int,\CorporateAid\Commerce\Models\Category>
     */
    private function resolveNavCategories(): array
    {
        try {
            $repo = $this->container->get(
                \CorporateAid\Commerce\Repositories\CategoryRepository::class
            );
            return $repo->listAll(false);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The current store, or null if the store context hasn't been
     * populated.
     */
    /**
     * The current store, or null if the store context hasn't been
     * populated.
     *
     * Protected so subclasses that render outside the storefront
     * layout (feed endpoints, JSON APIs) can resolve the store
     * without reimplementing the query.
     */
    protected function resolveStore(): ?\CorporateAid\Commerce\Models\Store
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
