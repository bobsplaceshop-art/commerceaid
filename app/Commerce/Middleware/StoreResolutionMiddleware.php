<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Middleware;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Models\Store;
use CorporateAid\Commerce\Services\StoreService;

/**
 * Resolves which store the current request serves, populates
 * StoreContext.
 *
 * Resolution order:
 *   1. Explicit - admin session store switcher
 *   2. Domain   - commerce_store_domains.hostname match
 *   3. Path     - /s/{slug}/... prefix (dev / staging fallback)
 *   4. Default  - the single active store, if exactly one exists
 *   5. None     - StoreContext stays empty; downstream code throws
 *                 StoreContextMissingException if it needs a store
 *
 * Runs early in the pipeline. Never throws on missing store - that
 * decision belongs to the controller.
 */
final class StoreResolutionMiddleware
{
    private string $lastSource = 'none';

    public function __construct(
        private StoreService $stores,
        private StoreContext $context
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $this->context->clear();
        $this->lastSource = 'none';

        $store = $this->resolveFromSession()
              ?? $this->resolveFromDomain($request)
              ?? $this->resolveFromPath($request)
              ?? $this->resolveFromDefault();

        if ($store !== null) {
            $this->context->set(
                $store->id,
                $store->slug,
                $this->lastSource
            );
        }

        return $next($request);
    }

    private function resolveFromSession(): ?Store
    {
        if (PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $storeId = (int) ($_SESSION['ca_active_store_id'] ?? 0);
        if ($storeId <= 0) {
            return null;
        }
        $store = $this->stores->resolveById($storeId);
        if ($store !== null) {
            $this->lastSource = 'session';
        }
        return $store;
    }

    private function resolveFromDomain(Request $request): ?Store
    {
        $host = strtolower((string) ($request->header('Host') ?? ($_SERVER['HTTP_HOST'] ?? '')));
        if (($pos = strpos($host, ':')) !== false) {
            $host = substr($host, 0, $pos);
        }
        if ($host === '') {
            return null;
        }

        $store = $this->stores->resolveByHostname($host);
        if ($store !== null) {
            $this->lastSource = 'domain';
        }
        return $store;
    }

    private function resolveFromPath(Request $request): ?Store
    {
        $path = (string) ($request->path() ?? '');
        if (!preg_match('#^/s/([a-z0-9][a-z0-9-]{1,78}[a-z0-9])(?:/|$)#', $path, $m)) {
            return null;
        }
        $store = $this->stores->resolveBySlug($m[1]);
        if ($store !== null) {
            $this->lastSource = 'path';
        }
        return $store;
    }

    private function resolveFromDefault(): ?Store
    {
        $store = $this->stores->defaultStore();
        if ($store !== null) {
            $this->lastSource = 'default';
        }
        return $store;
    }
}
