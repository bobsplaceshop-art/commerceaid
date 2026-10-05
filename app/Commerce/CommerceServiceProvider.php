<?php
declare(strict_types=1);

namespace CorporateAid\Commerce;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Platform\Support\CodeGenerator;
use CorporateAid\Core\Router;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Middleware\CustomerAuthMiddleware;
use CorporateAid\Commerce\Middleware\SetupGateMiddleware;
use CorporateAid\Commerce\Middleware\StoreResolutionMiddleware;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\ProductImageRepository;
use CorporateAid\Commerce\Repositories\ProductReviewRepository;
use CorporateAid\Commerce\Repositories\FontRepository;
use CorporateAid\Commerce\Services\Fonts\FontDownloader;
use CorporateAid\Commerce\Repositories\StoreRepository;
use CorporateAid\Commerce\Repositories\CustomerAddressRepository;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\OrderItemRepository;
use CorporateAid\Commerce\Repositories\OrderStatusHistoryRepository;
use CorporateAid\Commerce\Repositories\LegalPageRepository;
use CorporateAid\Commerce\Services\PricingService;
use CorporateAid\Commerce\Services\ProductReviewService;
use CorporateAid\Commerce\Repositories\CustomerGroupPriceRepository;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;
use CorporateAid\Commerce\Repositories\AttributeRepository;
use CorporateAid\Commerce\Repositories\CategoryAttributeRepository;
use CorporateAid\Commerce\Services\CatalogService;
use CorporateAid\Commerce\Services\CheckoutService;
use CorporateAid\Commerce\Repositories\CartItemRepository;
use CorporateAid\Commerce\Repositories\CartRepository;
use CorporateAid\Commerce\Services\CartService;
use CorporateAid\Commerce\Repositories\WishlistRepository;
use CorporateAid\Commerce\Services\WishlistService;
use CorporateAid\Commerce\Repositories\RecentViewRepository;
use CorporateAid\Commerce\Repositories\CustomerNotificationRepository;
use CorporateAid\Commerce\Repositories\MediaRepository;
use CorporateAid\Commerce\Support\MediaProcessor;
use CorporateAid\Commerce\Services\RecentViewService;
use CorporateAid\Commerce\Services\CustomerNotificationService;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Services\StoreService;

/**
 * Commerce Aid product provider.
 *
 * This is the ONLY class App\Core\App needs to know about for the
 * Commerce product. App.php calls:
 *   - register($container)
 *   - routes($router)
 *
 * Adding HR Aid, CRM Aid, or any future product follows the same
 * pattern: one provider class, two methods, zero changes to Core.
 */
final class CommerceServiceProvider
{
    public function register(Container $c): void
    {
        // ---------- Context ----------
        $c->singleton(StoreContext::class, fn() => new StoreContext());

        // ---------- Repositories: store ----------
        $c->singleton(StoreRepository::class,
            fn() => new StoreRepository($c->get(Database::class)));

        // ---------- Repositories: catalog ----------
        $c->singleton(CategoryRepository::class,
            fn() => new CategoryRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(BrandRepository::class,
            fn() => new BrandRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(ProductRepository::class,
            fn() => new ProductRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(ProductImageRepository::class,
            fn() => new ProductImageRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(ProductReviewRepository::class,
            fn() => new ProductReviewRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(ProductReviewService::class,
            fn() => new ProductReviewService(
                $c->get(ProductReviewRepository::class),
                $c->get(OrderRepository::class)
            ));

        // The App is not bound in the container (by design,
        // it IS the container owner). Capture the base path
        // once at registration time so the binding closure
        // does not need to resolve the App at request time.
        $appBasePath = \CorporateAid\Core\App::instance()->basePath();

        $c->singleton(FontDownloader::class,
            fn() => new FontDownloader(
                $c->get(\CorporateAid\Platform\Repositories\FileRepository::class),
                $appBasePath
            ));

        $c->singleton(FontRepository::class,
            fn() => new FontRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(InventoryRepository::class,
            fn() => new InventoryRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(ProductVariantRepository::class,
            fn() => new ProductVariantRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class),
                $c->get(InventoryRepository::class)
            ));

        // Attribute definitions are store-independent, so no StoreContext
        $c->singleton(AttributeRepository::class,
            fn() => new AttributeRepository($c->get(Database::class)));

        $c->singleton(CategoryAttributeRepository::class,
            fn() => new CategoryAttributeRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        // ---------- Services: store ----------
        $c->singleton(StoreService::class,
            fn() => new StoreService($c->get(StoreRepository::class)));

        // ---------- Services: customer auth ----------
        //
        // Uses the customer session, not the admin session. The
        // 'customer_session' binding is registered at the end of this
        // method (see below).
        $c->singleton(CustomerAuthService::class,
            fn() => new CustomerAuthService(
                $c->get(CustomerRepository::class),
                $c->get('customer_session')
            ));

        // Cart uses the customer session, not the admin session.
        // Guests and logged-in customers both resolve through
        // CartService; the session binding is what determines
        // where guest cart items are stored.
        $c->singleton(CartService::class,
            fn() => new CartService(
                $c->get('customer_session'),
                $c->get(CartRepository::class),
                $c->get(CartItemRepository::class),
                $c->get(ProductRepository::class),
                $c->get(ProductVariantRepository::class),
                $c->get(InventoryRepository::class),
                $c->get(CustomerAuthService::class)
            ));

        // Wishlist uses the customer session for guest state,
        // mirroring the cart. Same session binding, same merge-
        // on-login pattern. See docs/stages/008g.1_wishlist-plan.md.
        $c->singleton(WishlistService::class,
            fn() => new WishlistService(
                $c->get('customer_session'),
                $c->get(WishlistRepository::class),
                $c->get(ProductRepository::class),
                $c->get(CustomerAuthService::class)
            ));

        // Recently-viewed uses the customer session for guest
        // state, mirroring the cart and wishlist. Called on
        // every product detail page load.
        // See docs/stages/008g.2_recently-viewed-plan.md.
        $c->singleton(RecentViewService::class,
            fn() => new RecentViewService(
                $c->get('customer_session'),
                $c->get(RecentViewRepository::class),
                $c->get(CustomerAuthService::class)
            ));

        // Customer notifications. No session dependency -
        // notifications are per-customer, not per-session.
        // Guests do not have a message center.
        // See docs/stages/008g.3_message-center-plan.md.
        $c->singleton(CustomerNotificationService::class,
            fn() => new CustomerNotificationService(
                $c->get(CustomerNotificationRepository::class),
                $c->get(CustomerRepository::class)
            ));

        // ---------- Services: catalog ----------
        $c->singleton(CatalogService::class,
            fn() => new CatalogService(
                $c->get(ProductRepository::class),
                $c->get(CategoryRepository::class),
                $c->get(BrandRepository::class),
                $c->get(InventoryRepository::class)
            ));

        // ---------- B2B: customer groups + pricing ----------
        $c->singleton(CustomerGroupRepository::class,
            fn() => new CustomerGroupRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(CustomerGroupPriceRepository::class,
            fn() => new CustomerGroupPriceRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(PricingService::class,
            fn() => new PricingService(
                $c->get(ProductRepository::class),
                $c->get(ProductVariantRepository::class),
                $c->get(CustomerGroupPriceRepository::class)
            ));

        // ---------- Services: checkout ----------
        $c->singleton(CheckoutService::class,
            fn() => new CheckoutService(
                $c->get(Database::class),
                $c->get(CartService::class),
                $c->get(OrderRepository::class),
                $c->get(OrderItemRepository::class),
                $c->get(OrderStatusHistoryRepository::class),
                $c->get(InventoryRepository::class),
                $c->get(LegalPageRepository::class),
                $c->get(CustomerGroupRepository::class),
                $c->get(CustomerAddressRepository::class),
                $c->get(CodeGenerator::class),
                $c->get(StoreContext::class),
                $c->get(StoreRepository::class),
                $c->get(CustomerRepository::class),
                $c->get(StoreSettingsRepository::class),
                $c->get(CustomerNotificationService::class)
            ));

        // ---------- Customers ----------
        $c->singleton(CustomerRepository::class,
            fn() => new CustomerRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(OrderRepository::class,
            fn() => new OrderRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(OrderItemRepository::class,
            fn() => new OrderItemRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(OrderStatusHistoryRepository::class,
            fn() => new OrderStatusHistoryRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(LegalPageRepository::class,
            fn() => new LegalPageRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(StoreSettingsRepository::class,
            fn() => new StoreSettingsRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(CustomerAddressRepository::class,
            fn() => new CustomerAddressRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(CartRepository::class,
            fn() => new CartRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(CartItemRepository::class,
            fn() => new CartItemRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(WishlistRepository::class,
            fn() => new WishlistRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(RecentViewRepository::class,
            fn() => new RecentViewRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(CustomerNotificationRepository::class,
            fn() => new CustomerNotificationRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        // ---------- Media library ----------
        //
        // MediaRepository composes the Platform FileService
        // (for uploads), FileRepository (for core_files
        // inserts), and MediaProcessor (for EXIF stripping
        // and variant generation).
        //
        // Neither MediaRepository nor MediaProcessor is bound
        // anywhere else. The container does not auto-construct
        // classes without explicit bindings, so both must be
        // registered here.
        $c->singleton(MediaProcessor::class,
            fn() => new MediaProcessor());

        $c->singleton(MediaRepository::class,
            fn() => new MediaRepository(
                $c->get(Database::class),
                $c->get(\CorporateAid\Platform\Services\FileService::class),
                $c->get(\CorporateAid\Platform\Repositories\FileRepository::class),
                $c->get(MediaProcessor::class)
            ));

        // ---------- Middleware ----------
        $c->singleton(StoreResolutionMiddleware::class,
            fn() => new StoreResolutionMiddleware(
                $c->get(StoreService::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(SetupGateMiddleware::class,
            fn() => new SetupGateMiddleware(
                $c->get(Auth::class),
                $c->get(StoreService::class),
                $c->get(StoreContext::class),
                $c->get(Session::class)
            ));

        // Customer-side guard. Uses CustomerAuthService (customer
        // session), not Core\Auth (admin session). The loginPath
        // default is /account/login - distinct from the admin
        // /login. Route files reference this as the 'customer.auth'
        // alias.
        $c->singleton(CustomerAuthMiddleware::class,
            fn() => new CustomerAuthMiddleware(
                $c->get(CustomerAuthService::class)
            ));

        // ---------------------------------------------------------------
        // Customer session - separate cookie from the admin session.
        //
        // The admin session (bound to Session::class in App.php) uses
        // the cookie named in config/app.php -> session.name (default
        // 'ca_session'). Customer-side code resolves 'customer_session'
        // instead, which is the SAME Session class with the SAME config
        // except for one thing: the cookie name.
        //
        // Two cookies, one user, zero state cross-contamination. A
        // shopper logged into the storefront and an admin logged into
        // the dashboard can be the same browser, same tab, no
        // collision.
        //
        // Resolved by name (string key 'customer_session'), not by
        // class. Admin code that resolves Session::class keeps getting
        // the admin session. Customer code resolves 'customer_session'
        // explicitly. No implicit swap, no risk of one leaking into
        // the other.
        //
        // See docs/stages/008c_customer-auth-plan.md.
        // ---------------------------------------------------------------
        $c->singleton('customer_session', function () use ($c) {
            $config = (array) $c->get('config')->get('app.session', []);
            $config['name'] = 'ca_customer_session';
            return new Session($config);
        });
    }

    /**
     * Register Commerce routes programmatically.
     * Routes live in routes/commerce.php which App::loadRoutes()
     * loads directly. This method exists for future programmatic
     * registration. Currently a no-op.
     */
    public function routes(Router $router): void
    {
    }
}
