<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Response;
use CorporateAid\Platform\Context\StoreContextInterface;
use CorporateAid\Platform\Support\AuditContext;
use CorporateAid\Platform\Support\AuditLogger;

/**
 * Logs every state-changing HTTP request.
 *
 * Only POST, PUT, PATCH, DELETE are logged. GET/HEAD/OPTIONS are
 * skipped (read-only, too noisy).
 *
 * The middleware runs AFTER the controller. It captures the request
 * metadata and the response status, then writes a single row to
 * audit_logs via AuditLogger.
 *
 * Controllers enrich the row by calling AuditContext::set() before
 * returning their Response. If they don't, entity_type and
 * entity_id are NULL - the row still records what happened.
 *
 * Failures inside the middleware never affect the response - the
 * audit log is best-effort.
 */
final class AuditLoggerMiddleware
{
    private const LOGGED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const SKIP_PATHS = ['/health', '/favicon.ico'];

    public function __construct(private Container $container) {}

    public function handle(Request $request, callable $next): Response
    {
        // Reset per-request context so long-running workers
        // (cron, queue) do not carry state between requests.
        AuditContext::clear();

        $method = strtoupper($request->method());

        // Run the rest of the stack. A downstream middleware
        // (PermissionMiddleware today) may throw instead of
        // returning a Response. When that happens, the audit
        // row must still be written - a rejected write is
        // still a write attempt, and the ledger is the point.
        // Log the failed attempt with the exception's status,
        // then rethrow so App::renderException() renders the
        // themed error page.
        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (HttpException $e) {
            $this->logAttempt($request, $method, $e->getStatusCode());
            throw $e;
        } catch (\Throwable $e) {
            $this->logAttempt($request, $method, 500);
            throw $e;
        }

        if (!in_array($method, self::LOGGED_METHODS, true)) {
            return $response;
        }

        $path = $request->path();
        foreach (self::SKIP_PATHS as $skip) {
            if (str_starts_with($path, $skip)) {
                return $response;
            }
        }

        try {
            /** @var AuditLogger $logger */
            $logger = $this->container->get(AuditLogger::class);
            /** @var Auth $auth */
            $auth = $this->container->get(Auth::class);

            $userId = $auth->user()?->id;

            // Store context, when a resolution has happened.
            // Null on CLI, on health checks, and on any
            // request that bypassed the StoreResolutionMiddleware
            // (none today, but the guard is defensive).
            $storeId = null;
            if ($this->container->has(StoreContextInterface::class)) {
                $ctx = $this->container->get(StoreContextInterface::class);
                if ($ctx->has()) {
                    $storeId = $ctx->idOrNull();
                }
            }

            $logger->log(
                $method,
                $path,
                $userId,
                $storeId,
                $this->safeIp($request),
                $this->safeUserAgent($request),
                $this->requestHash($request),
                $response->statusCode(),
                AuditContext::entityType(),
                AuditContext::entityId()
            );
        } catch (\Throwable $e) {
            // Best-effort - never break the response
        }

        return $response;
    }

    /**
     * Write one audit row for a state-changing request whose
     * downstream middleware threw instead of returning a
     * Response. Called from the catch block inside handle().
     * The status comes from the exception, not from a
     * Response object, because there is no Response to read.
     *
     * Best-effort: a failure to log is swallowed. The
     * exception is rethrown by the caller regardless.
     */
    private function logAttempt(Request $request, string $method, int $statusCode): void
    {
        if (!in_array($method, self::LOGGED_METHODS, true)) {
            return;
        }

        $path = $request->path();
        foreach (self::SKIP_PATHS as $skip) {
            if (str_starts_with($path, $skip)) {
                return;
            }
        }

        try {
            /** @var AuditLogger $logger */
            $logger = $this->container->get(AuditLogger::class);
            /** @var Auth $auth */
            $auth = $this->container->get(Auth::class);

            $userId = $auth->user()?->id;

            $storeId = null;
            if ($this->container->has(StoreContextInterface::class)) {
                $ctx = $this->container->get(StoreContextInterface::class);
                if ($ctx->has()) {
                    $storeId = $ctx->idOrNull();
                }
            }

            $logger->log(
                $method,
                $path,
                $userId,
                $storeId,
                $this->safeIp($request),
                $this->safeUserAgent($request),
                $this->requestHash($request),
                $statusCode,
                AuditContext::entityType(),
                AuditContext::entityId()
            );
        } catch (\Throwable $e) {
            // Best-effort - never break the exception path
        }
    }

    /**
     * $request->ip() returns a non-empty string by contract, but we
     * guard anyway - malformed HTTP can produce empty values in
     * edge cases, and audit rows should not be rejected for it.
     */
    private function safeIp(Request $request): ?string
    {
        $ip = $request->ip();
        return $ip !== '' ? $ip : null;
    }

    private function safeUserAgent(Request $request): ?string
    {
        $ua = $request->userAgent();
        return $ua !== '' ? $ua : null;
    }

    private function requestHash(Request $request): ?string
    {
        $body = $request->all();
        if (empty($body)) {
            return null;
        }
        // Sort keys so payload order doesn't affect the hash.
        ksort($body);
        $json = json_encode($body);
        if ($json === false) {
            return null;
        }
        return hash('sha256', $json);
    }
}
