<?php
declare(strict_types=1);

namespace CorporateAid\Commerce;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\Router;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Middleware\SetupGateMiddleware;
use CorporateAid\Commerce\Middleware\StoreResolutionMiddleware;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\StoreRepository;
use CorporateAid\Commerce\Repositories\CustomerAddressRepository;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Services\PricingService;
use CorporateAid\Commerce\Repositories\CustomerGroupPriceRepository;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;
use CorporateAid\Commerce\Repositories\AttributeRepository;
use CorporateAid\Commerce\Repositories\CategoryAttributeRepository;
use CorporateAid\Commerce\Services\CatalogService;
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

        $c->singleton(InventoryRepository::class,
            fn() => new InventoryRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(ProductVariantRepository::class,
            fn() => new ProductVariantRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
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

        // ---------- Customers ----------
        $c->singleton(CustomerRepository::class,
            fn() => new CustomerRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
            ));

        $c->singleton(CustomerAddressRepository::class,
            fn() => new CustomerAddressRepository(
                $c->get(Database::class),
                $c->get(StoreContext::class)
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
