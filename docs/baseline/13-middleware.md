# 13. Middleware

Section of: [BASELINE](../BASELINE.md)

## Available Aliases

store
  Class: StoreResolutionMiddleware
  Resolves the store for the current request and populates the
  StoreContext. Runs first on every admin route.

auth
  Class: AuthMiddleware
  Requires an authenticated admin. Redirects to /login if the
  session has no user.

csrf
  Class: CsrfMiddleware
  Validates the _csrf field on POST/PUT/PATCH/DELETE requests.
  Returns 419 on mismatch. Never used on GET.

audit
  Class: AuditLoggerMiddleware
  Logs every state-changing request. Auto-injected by the Router.
  Never listed in route definitions.

setup.gate
  Class: SetupGateMiddleware
  Redirects to /commerce/ca-admin/setup if the current store has
  not completed mandatory setup. Used only on routes that require
  a fully configured store.

## Standard Middleware Orders

    GET  /commerce/ca-admin/foo
         ['store', 'auth']

    GET  /commerce/ca-admin/setup/{tab}
         ['store', 'auth', 'setup.gate']

    POST /commerce/ca-admin/foo
         ['store', 'auth', 'csrf']

    POST /commerce/ca-admin/setup/{tab}
         ['store', 'auth', 'setup.gate', 'csrf']

The order matters. store runs before auth because auth may need the
store to resolve the user's store association. auth runs before csrf
because we do not want to validate CSRF for an unauthenticated
request (it would leak timing information). csrf runs last so it
sits closest to the controller.

## Never Include Audit

The Router auto-injects audit for every POST, PUT, PATCH, and DELETE
before the Kernel runs the pipeline. The audit middleware sits
outermost:

    audit -> store -> auth -> csrf -> controller

Including 'audit' in the route's middleware array causes it to be
listed twice. The Kernel would then run audit twice per request,
writing two rows to core_audit_logs for every write.

If you find 'audit' in a route definition, remove it.

## Why Audit Sits Outermost

The audit middleware logs every state-changing request, including
those that short-circuit before reaching the controller. If csrf
rejects a request with a 419, or permission rejects with a 403, or
the rate limiter rejects with a 429, the attempt is still logged.

That ordering is deliberate. Security-critical: a rejected write is
still a write attempt, and it must leave a trace.

## The Onion Model

Middleware runs as an onion. Each middleware receives the request
and a $next callable. It may:

1. Short-circuit by returning a Response without calling $next
2. Pass through by returning $next($request)
3. Wrap $next and inspect or modify the response after the
   downstream middleware and controller run

Audit uses option 3 to record the response status code. Auth and
csrf use option 1 to short-circuit.

## How the Kernel Runs the Pipeline

Kernel::run($middleware, $request, $handler):

1. Reverse the middleware array.
2. Reduce it into a single callable:

       $pipeline = array_reduce(
           array_reverse($middleware),
           function ($next, $alias) {
               return fn ($req) => $this->resolve($alias)->handle($req, $next);
           },
           $handler
       );

3. Call $pipeline($request).

The result is that the first middleware in the list is the
outermost wrapper. It runs first on the request and last on the
response.

## Middleware Signature

Every middleware implements:

    public function handle(Request $request, callable $next): Response

No other signature works with the Kernel. The Kernel calls
$middleware->handle($request, $next) directly.

## Adding a New Middleware

1. Create the class in app/Commerce/Middleware/ (or
   app/Platform/Middleware/ if it is product-neutral).
2. Implement handle(Request, callable): Response.
3. Register an alias in app/Core/Http/Kernel.php's $map array.
4. Use the alias in route definitions.

## Commerce Middleware

Commerce owns two aliases:

    store       StoreResolutionMiddleware
    setup.gate  SetupGateMiddleware

Both live in app/Commerce/Middleware/. They may use Platform
services (Auth, Session) and Core services (Request, Response).

## Platform Middleware

Platform owns the rest:

    auth        AuthMiddleware
    guest       GuestMiddleware
    csrf        CsrfMiddleware
    permission  PermissionMiddleware
    ratelimit   RateLimitMiddleware
    audit       AuditLoggerMiddleware

Platform middleware must not hardcode any product-specific path.
If a middleware needs to redirect to a product route, the target
is passed as a constructor argument:

    new GuestMiddleware($auth, '/commerce/ca-admin')

## How to Know Which Middleware to Use

For an admin route:

    GET:  ['store', 'auth']
    POST: ['store', 'auth', 'csrf']

For an admin setup route:

    GET:  ['store', 'auth', 'setup.gate']
    POST: ['store', 'auth', 'setup.gate', 'csrf']

For a public storefront route (Stage 008 onwards):

    GET:  ['store']
    POST: ['store', 'csrf']

For an auth-free route (login, manifest, offline):

    No middleware, or ['store'] if the store context is needed.

The audit middleware is never declared. It is always injected.
