<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Diagnostics;

use CorporateAid\Core\Database;

/**
 * Performance check — hits every GET route in the app, twice
 * per route (cold and warm), records timings, and returns a
 * pass/fail table.
 *
 * Usable from the CLI (bin/perf-check.php) and from the admin
 * (AdminDiagnosticsController). Both call ::run().
 */
final class PerformanceCheckService
{
    /** @var array<int, array{path:string, warm:int, cold:int}> */
    private array $routes = [];

    public function __construct(private Database $db) {}

    /**
     * Run the check against a base URL. Returns a summary array
     * with per-route results.
     *
     * @return array{
     *   base: string,
     *   started_at: string,
     *   finished_at: string,
     *   duration_ms: int,
     *   results: array<int, array{
     *     path: string, method: string,
     *     status: int, cold_ms: int, warm_ms: int,
     *     cold_budget: int, warm_budget: int,
     *     pass: bool, error: ?string
     *   }>,
     *   failures: int,
     *   totals: array{routes:int, pass:int, fail:int}
     * }
     */
    public function run(string $baseUrl, ?string $cookieJar = null): array
    {
        $startedAt = date('c');
        $started   = microtime(true);

        $this->loadRoutes();
        $cookieJar = $cookieJar ?: tempnam(sys_get_temp_dir(), 'perf_');

        // Log in once if any admin routes are in the list.
        if ($this->needsAuth()) {
            $this->login($baseUrl, $cookieJar);
        }

        $results = [];

        foreach ($this->routes as $route) {
            $url = rtrim($baseUrl, '/') . $route['path'];

            $cold = $this->measure($url, $cookieJar);
            $warm = $this->measure($url, $cookieJar);

            $coldOk = $cold['status'] === 200 && $cold['ms'] <= $route['cold'];
            $warmOk = $warm['status'] === 200 && $warm['ms'] <= $route['warm'];
            $pass   = $coldOk && $warmOk;

            $results[] = [
                'path'        => $route['path'],
                'method'      => 'GET',
                'status'      => $warm['status'],
                'cold_ms'     => $cold['ms'],
                'warm_ms'     => $warm['ms'],
                'cold_budget' => $route['cold'],
                'warm_budget' => $route['warm'],
                'pass'        => $pass,
                'error'       => $warm['error'],
            ];
        }

        $failures = array_filter($results, fn($r) => !$r['pass']);
        $finishedAt = date('c');
        $duration   = (int) round((microtime(true) - $started) * 1000);

        if (is_file($cookieJar)) @unlink($cookieJar);

        return [
            'base'        => $baseUrl,
            'started_at'  => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $duration,
            'results'     => $results,
            'failures'    => count($failures),
            'totals'      => [
                'routes' => count($results),
                'pass'   => count($results) - count($failures),
                'fail'   => count($failures),
            ],
        ];
    }

    // -----------------------------------------------------------------

    private function loadRoutes(): void
    {
        $basePath   = dirname(__DIR__, 4);
        $routeFiles = glob($basePath . '/routes/*.php') ?: [];
        $fixtures   = $this->resolveFixtures();
        $routes     = [];
        $seen       = [];

        foreach ($routeFiles as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) continue;

            if (!preg_match_all(
                "/\\\$router->get\(\s*'([^']+)'/",
                $contents,
                $m
            )) continue;

            foreach ($m[1] as $pattern) {
                // Skip routes that reference webhooks or that need
                // params we cannot resolve.
                if (str_starts_with($pattern, '/webhooks/')) continue;
                if (str_starts_with($pattern, '/api/'))      continue;

                $resolved = $this->substituteFixtures($pattern, $fixtures);
                if ($resolved === null) continue;
                if (preg_match('/\{[^}]+\}/', $resolved)) continue;
                if (isset($seen[$resolved])) continue;

                $seen[$resolved] = true;

                // Budgets: admin routes get more breathing room;
                // storefront routes get tighter.
                $isAdmin = str_starts_with($resolved, '/commerce/ca-admin');
                $cold    = $isAdmin ? 30000 : 15000;
                $warm    = $isAdmin ? 5000  : 3000;

                $routes[] = [
                    'path' => $resolved,
                    'warm' => $warm,
                    'cold' => $cold,
                ];
            }
        }

        $this->routes = $routes;
    }

    private function needsAuth(): bool
    {
        foreach ($this->routes as $r) {
            if (str_starts_with($r['path'], '/commerce/ca-admin')) {
                return true;
            }
        }
        return false;
    }

    private function login(string $baseUrl, string $cookieJar): void
    {
        // 1. Fetch the login page to grab a fresh CSRF token.
        $ch = curl_init(rtrim($baseUrl, '/') . '/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $cookieJar,
            CURLOPT_COOKIEFILE     => $cookieJar,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!is_string($html)) return;
        if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) return;

        // 2. Post the login form.
        $ch = curl_init(rtrim($baseUrl, '/') . '/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                '_csrf'    => $m[1],
                'email'    => 'admin@anayaglobal.test',
                'password' => 'ChangeMe!2026',
            ]),
            CURLOPT_COOKIEJAR      => $cookieJar,
            CURLOPT_COOKIEFILE     => $cookieJar,
            CURLOPT_TIMEOUT        => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /** @return array{status:int, ms:int, error:?string} */
    private function measure(string $url, string $cookieJar): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_COOKIEJAR      => $cookieJar,
            CURLOPT_COOKIEFILE     => $cookieJar,
            CURLOPT_USERAGENT      => 'Anaya-Perf/1.0',
        ]);

        $start  = microtime(true);
        $body   = curl_exec($ch);
        $ms     = (int) round((microtime(true) - $start) * 1000);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error !== '') {
            return ['status' => 0, 'ms' => 0, 'error' => $error];
        }
        return ['status' => $status, 'ms' => $ms, 'error' => null];
    }

    /** @return array<string, string> */
    private function resolveFixtures(): array
    {
        try {
            $out = [];

            $row = $this->db->selectOne(
                "SELECT `slug` FROM `commerce_products` WHERE `status` = 'published' ORDER BY `id` ASC LIMIT 1"
            );
            $out['product'] = $row !== null ? (string) $row['slug'] : '';

            $row = $this->db->selectOne("SELECT `slug` FROM `commerce_categories` LIMIT 1");
            $out['category'] = $row !== null ? (string) $row['slug'] : '';

            $row = $this->db->selectOne("SELECT `id` FROM `commerce_products` LIMIT 1");
            $out['product_id'] = $row !== null ? (string) $row['id'] : '';

            $row = $this->db->selectOne("SELECT `order_number` FROM `commerce_orders` LIMIT 1");
            $out['order'] = $row !== null ? (string) $row['order_number'] : '';

            $row = $this->db->selectOne("SELECT `id` FROM `commerce_coupons` LIMIT 1");
            $out['coupon_id'] = $row !== null ? (string) $row['id'] : '';

            $row = $this->db->selectOne("SELECT `slug` FROM `commerce_forms` WHERE `status` = 'published' LIMIT 1");
            $out['form'] = $row !== null ? (string) $row['slug'] : '';

            $row = $this->db->selectOne("SELECT `slug` FROM `commerce_legal_pages` LIMIT 1");
            $out['legal'] = $row !== null ? (string) $row['slug'] : '';

            $out['page'] = 'home';

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Substitute placeholders in a route path, choosing fixtures
     * based on the route prefix. Different sections have different
     * slug/id namespaces:
     *
     *   /products/{slug}                    -> product slug
     *   /categories/{slug}                  -> category slug
     *   /forms/{slug}                       -> form slug
     *   /legal/{slug}                       -> legal page slug
     *   /commerce/ca-admin/coupons/{id}     -> coupon id
     *   /commerce/ca-admin/products/{id}    -> product id
     *   /commerce/ca-admin/orders/{orderNumber} -> order number
     *
     * The route prefix determines which fixture to use.
     *
     * @param array<string,string> $f
     */
    private function substituteFixtures(string $pattern, array $f): ?string
    {
        // Pick the fixture by prefix. Fall back to sensible
        // defaults if the prefix is not recognized.
        $slugFixture = match (true) {
            str_starts_with($pattern, '/forms/')    => $f['form']    ?? null,
            str_starts_with($pattern, '/legal/')    => $f['legal']   ?? null,
            str_starts_with($pattern, '/categories/') => $f['category'] ?? null,
            default                                 => $f['product'] ?? null,
        };

        $idFixture = match (true) {
            str_contains($pattern, '/coupons/')      => $f['coupon_id'] ?? null,
            default                                  => $f['product_id'] ?? null,
        };

        $map = [
            '{slug}'        => $slugFixture,
            '{category}'    => $f['category']     ?? null,
            '{id}'          => $idFixture,
            '{orderNumber}' => $f['order']        ?? null,
            '{page}'        => 'home',
        ];

        $out = $pattern;
        foreach ($map as $placeholder => $value) {
            if (str_contains($out, $placeholder)) {
                if ($value === null || $value === '') return null;
                $out = str_replace($placeholder, rawurlencode($value), $out);
            }
        }
        return $out;
    }
}
