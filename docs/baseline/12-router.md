# 12. Router Internals

Section of: [BASELINE](../BASELINE.md)

## Location

app/Core/Router.php. Instantiated once by App and used to register
routes from every file in the routes/ directory, then to dispatch
the incoming request.

## Registration API

    $router->get($path, $handler, $middleware = [])
    $router->post($path, $handler, $middleware = [])
    $router->put($path, $handler, $middleware = [])
    $router->patch($path, $handler, $middleware = [])
    $router->delete($path, $handler, $middleware = [])

All five methods delegate to a private add() that compiles the path
into a regex and stores the route in a per-method bucket.

## Handler Forms

Class form:

    [FooController::class, 'method']

The Router instantiates new FooController($container) and calls
$controller->method($request, $params).

Closure form:

    function (Request $request, array $params): Response { ... }

The Router calls the closure with the same two arguments. Use
this only for tiny one-off endpoints. Prefer the class form for
anything with business logic.

## Path Compilation

Path /commerce/ca-admin/foo/{id}/do compiles to:

    regex:  #^/commerce/ca-admin/foo/([^/]+)/do$#
    params: ['id']

Param names are captured during compilation. Regex captures are
always strings, so $params['id'] is '42' not 42.

Two rules follow from this:

1. Cast in the controller: $id = (int) ($params['id'] ?? 0);
2. Never rely on type juggling in the route definition.

Trailing slashes are stripped before compilation:

    /commerce/ca-admin/foo/  ->  /commerce/ca-admin/foo

So /commerce/ca-admin/foo and /commerce/ca-admin/foo/ both match.

## Dispatch Flow

For an incoming request, dispatch() does:

1. Read method and path from the Request.
2. Look up the routes bucket for the method.
3. For each route in registration order, test the regex against
   the path.
4. First match wins.
5. Collect the captured params into an associative array.
6. Auto-inject audit middleware if the method is POST, PUT, PATCH,
   or DELETE.
7. If the middleware array is empty after injection, call the
   handler directly.
8. Otherwise, hand the middleware array, request, and a wrapped
   handler to Kernel::run().

If no route matches, an HttpException::notFound() is thrown and
the error pipeline renders a 404 page.

## First-Match Wins

Registration order matters. Specific routes must come before
wildcards.

Wrong order:

    $router->get('/commerce/ca-admin/products/{id}', ...);
    $router->get('/commerce/ca-admin/products/create', ...);

The URL /commerce/ca-admin/products/create matches the first route
with id="create". The second route is never reached.

Right order:

    $router->get('/commerce/ca-admin/products/create', ...);
    $router->get('/commerce/ca-admin/products/{id}', ...);

## Automatic Audit Injection

Before calling the Kernel, dispatch() checks the method:

    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        array_unshift($middleware, 'audit');
    }

This means every state-changing request is audited, regardless of
what the route's middleware array contains. A route with middleware
['store', 'auth', 'csrf'] effectively runs:

    audit -> store -> auth -> csrf -> controller

The audit middleware sits outermost, so it captures the request even
when csrf, permission, or another middleware short-circuits with a
403 or 419.

Route authors never include 'audit' in the middleware array. Adding
it manually causes double-logging.

## Controller Instantiation

The invoke() method handles the two handler forms:

    private function invoke(mixed $handler, Request $request, array $params): Response
    {
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            $class  = $handler[0];
            $method = $handler[1];
            if (!class_exists($class)) {
                throw new \RuntimeException("Controller not found: {$class}");
            }
            $controller = new $class($this->container);
            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Method {$method} not found on {$class}");
            }
            return $controller->{$method}($request, $params);
        }

        if ($handler instanceof \Closure) {
            return $handler($request, $params);
        }

        throw new \RuntimeException('Invalid route handler.');
    }

Two implications for controllers:

1. The constructor must accept a Container as the first argument
   and pass it to the parent. AdminController does this. Subclasses
   never declare their own constructor.
2. Every route method signature is
   (Request $request, array $params = []): Response.

## Kernel Handoff

If the middleware array is non-empty after audit injection, the
Router hands off to Kernel::run():

    $kernel->run($middleware, $request, $handler)

The Kernel builds an onion pipeline where each middleware wraps the
next. The controller call is the innermost layer. Middleware can
act before and after the controller runs.

For an empty middleware array (which never happens for POST/PUT/
PATCH/DELETE because of audit injection, and only happens for GET
if the route declares no middleware), the handler is called
directly.

## Adding a Route

1. Open routes/commerce.php.
2. Locate or create the section for the domain.
3. Add the route with specific-before-wildcard order.
4. Do not include 'audit' in the middleware array.
5. Include 'csrf' in the middleware array for any write method.

## Things That Will Not Work

Regex anchors in the path string:

    /foo/^{id}$/  -- the {id} is matched literally, not as a param

Optional params:

    /foo/{id?}  -- the ? is captured as part of the param name regex,
                   which does not match

Multiple wildcards in one segment:

    /foo/{a}-{b}  -- compilePattern only handles one {name} per
                     place. Split into separate segments if you need
                     two values.

Query strings in the path:

    /foo?bar=baz  -- paths are stripped of query strings before
                     matching. Read query params from the Request.

Regex param names:

    /foo/{[a-z]+}  -- not supported. Params must be simple
                      [a-zA-Z_][a-zA-Z0-9_]* identifiers.
