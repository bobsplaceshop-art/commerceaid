<?php
declare(strict_types=1);

namespace CorporateAid\Core\Http;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Platform\Middleware\AuditLoggerMiddleware;
use CorporateAid\Platform\Middleware\AuthMiddleware;
use CorporateAid\Platform\Middleware\CsrfMiddleware;
use CorporateAid\Platform\Middleware\GuestMiddleware;
use CorporateAid\Platform\Middleware\PermissionMiddleware;
use CorporateAid\Platform\Middleware\RateLimitMiddleware;
use RuntimeException;

/**
 * Middleware registry + onion pipeline runner.
 *
 * Middleware specs are strings:
 *   'auth'                          → AuthMiddleware
 *   'guest'                         → GuestMiddleware
 *   'csrf'                          → CsrfMiddleware
 *   'permission:orders.view'        → PermissionMiddleware('orders.view')
 *   'ratelimit:login,5,15'          → RateLimitMiddleware('login', 5, 15)
 *   'audit'                         → AuditLoggerMiddleware
 *   'store'                         → Commerce\StoreResolutionMiddleware
 *   'setup.gate'                    → Commerce\SetupGateMiddleware
 *
 * Platform middlewares (auth, guest, csrf, permission, ratelimit, audit)
 * are registered by App::registerPlatformServices().
 *
 * Commerce middlewares (store, setup.gate) are registered by
 * CommerceServiceProvider::register().
 *
 * Adding a new middleware:
 *   1. Add the class to the owning layer
 *   2. Register it in that layer's provider
 *   3. Add its alias to $map below
 */
final class Kernel
{
    private Container $container;

    /** @var array<string, class-string> */
    private array $map = [
        // Platform
        'auth'       => AuthMiddleware::class,
        'guest'      => GuestMiddleware::class,
        'csrf'       => CsrfMiddleware::class,
        'permission' => PermissionMiddleware::class,
        'ratelimit'  => RateLimitMiddleware::class,
        'audit'      => AuditLoggerMiddleware::class,

        // Commerce
        'store'         => \CorporateAid\Commerce\Middleware\StoreResolutionMiddleware::class,
        'setup.gate'    => \CorporateAid\Commerce\Middleware\SetupGateMiddleware::class,
        'customer.auth' => \CorporateAid\Commerce\Middleware\CustomerAuthMiddleware::class,
        'maintenance'   => \CorporateAid\Commerce\Middleware\MaintenanceMiddleware::class,
    ];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Run the middleware pipeline around the controller callable.
     *
     * @param string[]                   $names   Middleware specs, outer-to-inner order
     * @param Request                    $request
     * @param callable(Request):Response $handler
     */
    public function run(array $names, Request $request, callable $handler): Response
    {
        $chain = $handler;

        foreach (array_reverse($names) as $spec) {
            $instance = $this->resolve($spec);
            $next = $chain;

            $chain = function (Request $r) use ($instance, $next): Response {
                return $this->invokeMiddleware($instance, $r, $next);
            };
        }

        return $chain($request);
    }

    private function invokeMiddleware(object $instance, Request $request, callable $next): Response
    {
        if (!method_exists($instance, 'handle')) {
            throw new RuntimeException('Middleware ' . get_class($instance) . ' has no handle() method.');
        }
        /** @var Response $response */
        $response = $instance->handle($request, $next);
        return $response;
    }

    private function resolve(string $spec): object
    {
        $parts = explode(':', $spec, 2);
        $name  = $parts[0];
        $arg   = $parts[1] ?? null;

        if (!isset($this->map[$name])) {
            throw new RuntimeException("Unknown middleware: {$spec}");
        }

        $class = $this->map[$name];

        if ($name === 'permission') {
            return new PermissionMiddleware($this->container->get(Auth::class), (string) $arg);
        }

        if ($name === 'ratelimit') {
            $bits = explode(',', (string) $arg);
            return new RateLimitMiddleware(
                $this->container->get(\CorporateAid\Platform\Support\RateLimiter::class),
                trim($bits[0] ?? ''),
                (int) ($bits[1] ?? 5),
                (int) ($bits[2] ?? 15)
            );
        }

        if (!$this->container->has($class)) {
            throw new RuntimeException(
                "Middleware not registered in container: {$class}. " .
                "Add it to the owning provider's register() method."
            );
        }

        return $this->container->get($class);
    }
}
