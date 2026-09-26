# 5. Routes

Section of: [BASELINE](../BASELINE.md)

## Template

    $router->get('/commerce/ca-admin/foo',
        [FooController::class, 'index'],
        ['store', 'auth']);

    $router->post('/commerce/ca-admin/foo/{id}/do',
        [FooController::class, 'do'],
        ['store', 'auth', 'csrf']);

## Rules

1. Specific routes before wildcards. /foo/create must precede
   /foo/{id}/edit or /foo/create will be captured as id="create".
2. Params: {id} compiles to the regex ([^/]+) and is captured as
   a string. Cast in the controller.
3. Middleware array: do not include 'audit'. The Router injects it
   automatically for POST/PUT/PATCH/DELETE at the outermost
   position. Adding it manually causes double-logging.
4. GET routes: ['store', 'auth'] or ['store', 'auth', 'setup.gate'].
5. POST routes: ['store', 'auth', 'csrf']. If setup.gate is also
   needed, it goes before csrf: ['store', 'auth', 'setup.gate', 'csrf'].
6. Class FQN or use statement at the top of the file. Both patterns
   appear in routes/commerce.php.

## Where Routes Are Registered

All Commerce routes live in routes/commerce.php. That file is loaded
by App::loadRoutes(), which iterates over every file in the routes/
directory and includes the ones that exist.

The ServiceProvider's routes() method is a no-op:

    public function routes(Router $router): void
    {
    }

Routes are never registered from the provider. If you find yourself
wanting to, that is a signal something else is wrong.

## Middleware Order

Middleware runs as an onion. The outermost middleware runs first on
the request and last on the response.

Standard orders:

    GET  /commerce/ca-admin/foo
         ['store', 'auth']

    GET  /commerce/ca-admin/setup/{tab}
         ['store', 'auth', 'setup.gate']

    POST /commerce/ca-admin/foo
         ['store', 'auth', 'csrf']

    POST /commerce/ca-admin/setup/{tab}
         ['store', 'auth', 'setup.gate', 'csrf']

Note that 'audit' is not listed. The Router prepends it for any
non-GET method before passing the array to the Kernel.

## Route File Structure

At the top of routes/commerce.php:

    <?php
    declare(strict_types=1);

    use CorporateAid\Core\Request;
    use CorporateAid\Core\Response;
    use CorporateAid\Core\Router;
    use CorporateAid\Commerce\Controllers\Pwa\ManifestController;
    use CorporateAid\Commerce\Controllers\Admin\DashboardController;
    // ... other controllers

    /** @var Router $router */

Then routes are registered in sections with comment banners:

    // =====================================================================
    // Admin - Products (Stage 006E)
    // =====================================================================
    $router->get('/commerce/ca-admin/products', ...);

## Handler Forms

Two forms are accepted:

    [FooController::class, 'method']
        The Router instantiates new FooController($container) and calls
        $controller->method($request, $params).

    Closure
        The Router calls $closure($request, $params) directly.

Prefer the class form for anything a controller should own. Use the
closure form only for tiny one-off endpoints like the /offline page.

## Param Capture

Path /commerce/ca-admin/foo/{id}/do compiles to the regex:

    #^/commerce/ca-admin/foo/([^/]+)/do$#

For the URL /commerce/ca-admin/foo/42/do, the match captures:

    $params = ['id' => '42']

The value is a string. Always cast:

    $id = (int) ($params['id'] ?? 0);

## Adding a New Route

1. Open routes/commerce.php.
2. Find the section for the domain (Admin - Products, Admin - Inventory,
   etc.) or create a new section with a comment banner.
3. Add the route with the correct middleware order.
4. Specific routes before wildcards.
5. Reload Apache to clear OPcache.
