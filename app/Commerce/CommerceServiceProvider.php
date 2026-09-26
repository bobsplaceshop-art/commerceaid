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
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Services\PricingService;
use CorporateAid\Commerce\Repositories\CustomerGroupPriceRepository;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\ProductVariantRepository;
use CorporateAid\Commerce\Repositories\AttributeRepository;
use CorporateAid\Commerce\Repositories\CategoryAttributeRepository;
use CorporateAid\Commerce\Services\CatalogService;
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
