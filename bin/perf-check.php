<?php
declare(strict_types=1);

/**
 * Performance smoke test.
 *
 * Hits every key URL twice (cold and warm), records the
 * wall-clock time for each, and asserts against per-route
 * thresholds. Warm overages fail the run.
 *
 * Usage:
 *   php bin/perf-check.php
 *   php bin/perf-check.php --base=http://localhost:8084
 *   php bin/perf-check.php --verbose
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$base    = 'http://localhost:8084';
$verbose = false;

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if (str_starts_with($arg, '--base=')) $base = substr($arg, 7);
    if ($arg === '--verbose')             $verbose = true;
}

$base = rtrim($base, '/');

// Route table. {SLUG} and {ID} are substituted with the
// first published entity from the dev database so the
// check works on a fresh install.
$routes = [
    ['path' => '/',                                         'warm' => 3000,  'cold' => 30000],
    ['path' => '/products',                                 'warm' => 3000,  'cold' => 15000],
    ['path' => '/categories',                               'warm' => 2000,  'cold' => 10000],
    ['path' => '/commerce/ca-admin',                        'warm' => 2000,  'cold' => 10000],
    ['path' => '/commerce/ca-admin/products',               'warm' => 3000,  'cold' => 15000],
    ['path' => '/commerce/ca-admin/pages',                  'warm' => 3000,  'cold' => 15000],
    ['path' => '/commerce/ca-admin/pages/home',             'warm' => 5000,  'cold' => 60000],
    ['path' => '/commerce/ca-admin/settings?tab=branding',  'warm' => 5000,  'cold' => 60000],
    ['path' => '/commerce/ca-admin/settings?tab=popup',     'warm' => 5000,  'cold' => 60000],
];

// Resolve the first product slug for the {SLUG} route.
$productSlug = resolveFirstProductSlug();
if ($productSlug !== '') {
    $routes[] = ['path' => '/products/' . $productSlug, 'warm' => 3000, 'cold' => 10000];
}

echo "Performance check against {$base}\n";
echo str_repeat('=', 90) . "\n";
printf("%-6s %-52s %-4s %-18s %-18s\n", 'RES', 'ROUTE', 'HTTP', 'COLD (budget)', 'WARM (budget)');
echo str_repeat('-', 90) . "\n";

$cookieJar = tempnam(sys_get_temp_dir(), 'perfck_');
$failures  = [];

// First request: real cold.
foreach ($routes as $route) {
    $url = $base . $route['path'];
    $r   = measure($url, $cookieJar);
    $ok  = $r['status'] === 200 && $r['ms'] <= $route['cold'];
    $results[$route['path']] = ['cold' => $r, 'route' => $route, 'ok' => $ok];
}

// Second request: warm. Fails the build if over warm budget.
foreach ($results as $path => &$row) {
    $url      = $base . $path;
    $r        = measure($url, $cookieJar);
    $row['warm'] = $r;
    $warmOk   = $r['status'] === 200 && $r['ms'] <= $row['route']['warm'];
    $coldOk   = $row['ok'];

    $mark = ($warmOk && $coldOk) ? 'PASS' : 'FAIL';

    printf(
        "%-6s %-52s %-4d %7dms (<=%5d)  %7dms (<=%5d)\n",
        $mark,
        $path,
        $r['status'],
        $row['cold']['ms'],
        $row['route']['cold'],
        $r['ms'],
        $row['route']['warm']
    );

    if (!$warmOk) {
        $failures[] = $path . ' (warm ' . $r['ms'] . 'ms > ' . $row['route']['warm'] . 'ms)';
    }
}
unset($row);

echo str_repeat('=', 90) . "\n";
@unlink($cookieJar);

if ($failures === []) {
    echo "All routes within warm budget.\n";
    exit(0);
}

echo "FAILED: " . count($failures) . " route(s) over warm budget:\n";
foreach ($failures as $f) {
    echo "  - {$f}\n";
}
exit(1);

// -----------------------------------------------------------------

/**
 * @return array{status:int, ms:int, error:?string, size:int}
 */
function measure(string $url, string $cookieJar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_USERAGENT      => 'Anaya-Perf-Check/1.0',
    ]);

    $start  = microtime(true);
    $body   = curl_exec($ch);
    $ms     = (int) round((microtime(true) - $start) * 1000);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    $size   = (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    curl_close($ch);

    if ($body === false || $error !== '') {
        return ['status' => 0, 'ms' => 0, 'error' => $error, 'size' => 0];
    }

    return ['status' => $status, 'ms' => $ms, 'error' => null, 'size' => $size];
}

function resolveFirstProductSlug(): string
{
    $basePath = dirname(__DIR__);
    $cfgFile  = $basePath . '/config/local.php';
    if (!is_file($cfgFile)) return '';
    $cfg = require $cfgFile;
    if (!isset($cfg['database'])) return '';
    $db = $cfg['database'];

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'] ?? '127.0.0.1',
        $db['port'] ?? 3306,
        $db['database'] ?? 'anaya_commerce'
    );

    try {
        $pdo = new PDO($dsn, $db['username'] ?? 'root', $db['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $row = $pdo->query("SELECT slug FROM commerce_products WHERE status = 'published' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (string) $row['slug'] : '';
    } catch (\Throwable) {
        return '';
    }
}
