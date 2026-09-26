# 14. Service Provider

Section of: [BASELINE](../BASELINE.md)

## Location

app/Commerce/CommerceServiceProvider.php. The only class that Core's
App bootstrapper knows about for the Commerce product.

## The Contract

    namespace CorporateAid\Commerce;

    use CorporateAid\Core\Container;
    use CorporateAid\Core\Router;

    final class CommerceServiceProvider
    {
        public function register(Container $c): void
        {
            // bind every Commerce service
        }

        public function routes(Router $router): void
        {
            // no-op
        }
    }

App::registerProductProviders() instantiates the provider and calls
register($container). App::loadRoutes() calls routes($router).

## What register() Does

Every Commerce service, repository, and context is bound here as a
singleton. Bindings are lazy: the closure runs only when the
container is asked for the class.

Standard pattern:

    $c->singleton(FooRepository::class,
        fn() => new FooRepository(
            $c->get(Database::class),
            $c->get(StoreContext::class)
        ));

Store-independent repositories omit the StoreContext:

    $c->singleton(AttributeRepository::class,
        fn() => new AttributeRepository($c->get(Database::class)));

Services receive their dependencies the same way:

    $c->singleton(CatalogService::class,
        fn() => new CatalogService(
            $c->get(ProductRepository::class),
            $c->get(CategoryRepository::class),
            $c->get(BrandRepository::class),
            $c->get(InventoryRepository::class)
        ));

## What routes() Does

Nothing. Routes live in routes/commerce.php and are loaded by
App::loadRoutes() directly. The routes() method exists only to
satisfy the provider contract.

If you find yourself wanting to register a route from the provider,
that is a signal something else is wrong. Add it to
routes/commerce.php instead.

## Binding Order

Order does not matter. Every binding is a closure, so the container
resolves dependencies recursively at fetch time. A repository can
be bound before the StoreContext it depends on; the closure runs
later and the StoreContext is resolved then.

## Current Bindings

Context:

    StoreContext

Repositories - catalog:

    StoreRepository
    CategoryRepository
    BrandRepository
    ProductRepository
    InventoryRepository
    ProductVariantRepository
    AttributeRepository
    CategoryAttributeRepository

Repositories - B2B:

    CustomerGroupRepository
    CustomerGroupPriceRepository
    CustomerRepository

Services:

    StoreService
    CatalogService
    PricingService

Middleware:

    StoreResolutionMiddleware
    SetupGateMiddleware

## Adding a New Repository

Three changes to the provider, all in one file:

1. Add a use statement at the top:

       use CorporateAid\Commerce\Repositories\FooRepository;

2. Add one singleton block in register():

       $c->singleton(FooRepository::class,
           fn() => new FooRepository(
               $c->get(Database::class),
               $c->get(StoreContext::class)
           ));

3. Nothing else. App.php is untouched. routes/commerce.php is
   untouched.

## Adding a New Service

Same pattern, but the constructor usually takes repositories:

    $c->singleton(FooService::class,
        fn() => new FooService(
            $c->get(FooRepository::class),
            $c->get(StoreContext::class)
        ));

## Singleton vs Bind

Every binding uses singleton(). The container returns the same
instance on repeated fetches within a request.

There is no transient binding pattern in this codebase. If a class
needs a fresh instance per fetch, that is a signal it should be a
value object constructed directly, not fetched from the container.

## What Never Goes in the Provider

Do not bind:

- Controllers. The Router instantiates them directly with the
  container as the first argument.
- Models. They are DTOs, constructed via fromRow().
- Config values. Those live in config/*.php.
- Route definitions. Those live in routes/*.php.
- Middleware aliases. Those live in the Kernel's $map array.

The provider is the registry of services, not a catch-all for
everything.

## What Belongs in Core

If a binding is not Commerce-specific, it does not belong in
CommerceServiceProvider. Core and Platform bindings live in their
respective registration points:

- Core services: bound in App::registerCoreServices()
- Platform services: bound in App::registerPlatformServices()
- Commerce services: bound in CommerceServiceProvider::register()

The layering rule applies to bindings. A Commerce binding may
reference a Core or Platform class. A Core binding may never
reference a Commerce class.

## Verifying a Binding

After adding a repository, resolve it:

    $repo = $app->container()->get(FooRepository::class);

If the class file exists and the binding is correct, this returns
the singleton. If the class does not exist, Composer's autoloader
throws "Class not found". If the binding is missing, the container
throws "No binding for ...".

Run composer dump-autoload -o after adding any new class file.
