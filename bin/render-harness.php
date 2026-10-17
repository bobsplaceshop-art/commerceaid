#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/render-harness.php - responsive render check.
 *
 * The runtime sibling to bin/check-responsive.php. The
 * static checker walks view files; this harness drives a
 * real Chrome browser, navigates every tier-1 static
 * route at four viewports, and checks each page for
 * horizontal overflow.
 *
 * The check:
 *
 *     document.documentElement.scrollWidth
 *         > document.documentElement.clientWidth
 *
 * A 2px tolerance absorbs scrollbar and rounding noise.
 * `innerWidth` is deliberately not used: on Windows
 * the vertical scrollbar eats into clientWidth, not
 * scrollWidth, and comparing to innerWidth would make
 * every page with a vertical scrollbar a false
 * positive.
 *
 * The harness uses Chrome headless driven over the
 * Chrome DevTools Protocol (CDP). The CDP client lives
 * at bin/render-harness-cdp.php. It is hand-rolled
 * because the codebase has no Node.js and every
 * headless-browser automation library assumes Node.
 *
 * Login:
 *
 *   The harness logs in once at the start of the run
 *   using PHP's own curl, not Chrome. The flow:
 *
 *     1. GET  /login       scrape _csrf and any cookies
 *     2. POST /login       credentials + _csrf
 *     3. Read the session cookie from the response
 *     4. Network.setCookie in Chrome
 *
 *   The harness then navigates Chrome only to pages
 *   whose rendered layout it wants to check. It never
 *   drives the login form through the browser.
 *
 * Route filter:
 *
 *   - Any path containing { is skipped (tier 2).
 *   - /login, /logout, /logout/done, /password/* are
 *     skipped (they alter the session).
 *   - /webhooks/* are skipped (POST only, defensive).
 *
 *   A navigation whose final URL differs from the
 *   requested path (a login redirect, a 404) aborts
 *   the run with exit code 2. Silent partial runs are
 *   worse than loud aborts.
 *
 * Exemptions:
 *
 *   The known-scrolling containers from Stage 014.
 *   Listed in is_exempt() below.
 *
 * Exit codes:
 *   0   no violations
 *   1   at least one violation
 *   2   the harness could not run (Chrome missing,
 *       Apache not reachable, login failed, redirect)
 *
 * Usage:
 *   php bin/render-harness.php                       full run
 *   php bin/render-harness.php --route=/cart         one route, all viewports
 *   php bin/render-harness.php --viewport=375        all routes, one viewport
 *   php bin/render-harness.php --route=/cart --viewport=375
 *   php bin/render-harness.php --verbose             per-navigation lines
 *   php bin/render-harness.php --no-login            skip admin routes
 *
 * See docs/stages/015_render-harness-plan.md.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$basePath = dirname(__DIR__);

require __DIR__ . '/render-harness-cdp.php';

// ---------------------------------------------------------------------
// Flags
// ---------------------------------------------------------------------
$filterRoute     = null;
$filterViewport  = null;
$verbose         = false;
$skipLogin       = false;

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if (str_starts_with($arg, '--route=')) {
        $filterRoute = substr($arg, 8);
    } elseif (str_starts_with($arg, '--viewport=')) {
        $filterViewport = (int) substr($arg, 11);
    } elseif ($arg === '--verbose') {
        $verbose = true;
    } elseif ($arg === '--no-login') {
        $skipLogin = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php bin/render-harness.php [--route=/path] [--viewport=N] [--verbose] [--no-login]\n");
        exit(0);
    }
}

// ---------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------
$localFile = $basePath . '/config/local.php';
$local = is_file($localFile) ? require $localFile : [];

$chromePath = (string) ($local['render']['chrome_path']
    ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe');
$baseUrl = rtrim((string) ($local['render']['base_url']
    ?? 'http://localhost:8084'), '/');
$adminEmail = (string) ($local['render']['admin_email']
    ?? 'admin@anayaglobal.test');
$adminPassword = (string) ($local['render']['admin_password']
    ?? 'ChangeMe!2026');

if (!is_file($chromePath)) {
    fwrite(STDERR, "Chrome not found at: {$chromePath}\n");
    fwrite(STDERR, "Set render.chrome_path in config/local.php.\n");
    exit(2);
}

// The viewports. Same four as Stage 014's protocol.
$allViewports = [1440, 1024, 768, 375];
if ($filterViewport !== null) {
    if (!in_array($filterViewport, $allViewports, true)) {
        fwrite(STDERR, "Unknown viewport: {$filterViewport}. Expected 1440, 1024, 768, or 375.\n");
        exit(2);
    }
    $viewports = [$filterViewport];
} else {
    $viewports = $allViewports;
}

// ---------------------------------------------------------------------
// The route extraction. Static parse of the route files.
// ---------------------------------------------------------------------
function extract_get_routes(string $basePath): array
{
    $paths = [];
    $files = glob($basePath . '/routes/*.php') ?: [];
    foreach ($files as $file) {
        $src = file_get_contents($file);
        if ($src === false) continue;
        // Match $router->get('/path', ...) across newlines.
        if (preg_match_all(
            '/\$router->get\(\s*\'([^\']+)\'/s',
            $src,
            $matches
        )) {
            foreach ($matches[1] as $path) {
                $paths[$path] = true;
            }
        }
    }
    return array_keys($paths);
}

/**
 * Routes that redirect by design, return non-HTML, or
 * require state (a cart, a wizard step, a payment token)
 * the harness does not seed. Attempting them would report
 * a redirect as a violation. Skipped unconditionally.
 *
 * Each entry here is a documented decision, not a
 * limitation of the harness. The comment names the
 * reason. A future session that seeds the required state
 * removes the entry.
 */
const SKIP_BY_DESIGN = [
    // Setup wizard redirects to the first tab on a
    // fully-configured store.
    '/commerce/ca-admin/setup',
    '/commerce/ca-admin/setup/skip',

    // CSV / non-HTML endpoints. The browser never fires
    // Page.loadEventFired on a non-HTML body.
    '/commerce/ca-admin/inventory/export.csv',
    '/commerce/ca-admin/inventory/movements/export.csv',
    '/commerce/ca-admin/subscribers/export',

    // Token-required routes. Without a valid token in the
    // query string, the controller redirects.
    '/account/reset-password',

    // Wizard-state redirects. Without a populated cart
    // or prior wizard step, the controller redirects to
    // /cart or /checkout/info.
    '/search',
    '/checkout',
    '/checkout/shipping',
    '/checkout/payment',
    '/checkout/confirm',
    '/checkout/stripe/return',
    '/checkout/paypal/return',
    '/checkout/pesapal/return',
    '/checkout/flutterwave/return',
];

/**
 * Routes that require a customer session. The harness
 * logs in as an admin; the customer routes redirect to
 * /account/login. A future run with a customer login
 * removes this list.
 *
 * The public customer routes (/account/register,
 * /account/login, /account/forgot-password) are NOT
 * in this list. They render for anonymous users and
 * are checked.
 */
const SKIP_WITHOUT_CUSTOMER = [
    '/account',
    '/account/orders',
    '/account/addresses',
    '/account/addresses/create',
    '/account/password',
    '/account/claims',
    '/account/wishlist',
    '/account/recent',
    '/account/messages',
    '/account/messages/preferences',
    '/account/returns',
];

function filter_routes(array $all, bool $skipLogin): array
{
    $out = [];
    foreach ($all as $path) {
        if (str_contains($path, '{')) continue;
        if ($path === '/login') continue;
        if (str_starts_with($path, '/logout')) continue;
        if (str_starts_with($path, '/password/')) continue;
        if (str_starts_with($path, '/webhooks/')) continue;
        if ($skipLogin && str_starts_with($path, '/commerce/ca-admin')) continue;
        if (in_array($path, SKIP_BY_DESIGN, true)) continue;
        if (in_array($path, SKIP_WITHOUT_CUSTOMER, true)) continue;
        $out[] = $path;
    }
    return $out;
}

/**
 * The count of routes the filter removed because of the
 * two skip lists. Printed in the summary so the operator
 * sees how many routes were skipped by decision versus
 * how many were checked.
 */
function count_skipped(array $all, bool $skipLogin): int
{
    $byDesign = 0;
    $withoutCustomer = 0;
    foreach ($all as $path) {
        if (str_contains($path, '{')) continue;
        if ($path === '/login') continue;
        if (str_starts_with($path, '/logout')) continue;
        if (str_starts_with($path, '/password/')) continue;
        if (str_starts_with($path, '/webhooks/')) continue;
        if ($skipLogin && str_starts_with($path, '/commerce/ca-admin')) continue;
        if (in_array($path, SKIP_BY_DESIGN, true)) { $byDesign++; continue; }
        if (in_array($path, SKIP_WITHOUT_CUSTOMER, true)) { $withoutCustomer++; continue; }
    }
    return $byDesign + $withoutCustomer;
}

$allRoutes = filter_routes(extract_get_routes($basePath), $skipLogin);

if ($filterRoute !== null) {
    if (!in_array($filterRoute, $allRoutes, true)) {
        fwrite(STDERR, "Route not in tier-1 set: {$filterRoute}\n");
        exit(2);
    }
    $routes = [$filterRoute];
} else {
    $routes = $allRoutes;
}

if ($routes === []) {
    fwrite(STDERR, "No tier-1 routes to check.\n");
    exit(2);
}

// ---------------------------------------------------------------------
// Chrome lifecycle
// ---------------------------------------------------------------------
$userDataDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR
    . 'ca-render-harness-' . bin2hex(random_bytes(4));
@mkdir($userDataDir, 0777, true);

$chromeCmd = sprintf(
    '"%s" --headless=new --disable-gpu --no-sandbox'
    . ' --disable-extensions'
    . ' --remote-debugging-port=0'
    . ' --user-data-dir="%s"'
    . ' about:blank',
    $chromePath,
    $userDataDir
);

$chromeProcess = proc_open($chromeCmd, [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
], $chromePipes);

if (!is_resource($chromeProcess)) {
    fwrite(STDERR, "Failed to spawn Chrome.\n");
    exit(2);
}

// Wait for the debug port file.
$portFile = $userDataDir . DIRECTORY_SEPARATOR . 'DevToolsActivePort';
$deadline = microtime(true) + 15.0;
while (!is_file($portFile) && microtime(true) < $deadline) {
    usleep(50_000);
}
if (!is_file($portFile)) {
    fwrite(STDERR, "Chrome did not write DevToolsActivePort.\n");
    proc_terminate($chromeProcess);
    exit(2);
}

$portLines = file($portFile, FILE_IGNORE_NEW_LINES);
$debugPort = (int) ($portLines[0] ?? 0);

// ---------------------------------------------------------------------
// Login (unless skipped)
// ---------------------------------------------------------------------
$sessionCookie = null;

if (!$skipLogin) {
    // Step 1: GET /login, scrape _csrf, capture cookies.
    $cookieJar = tempnam(sys_get_temp_dir(), 'ca-harness-cookie-');
    if ($cookieJar === false) {
        fwrite(STDERR, "Could not create cookie jar.\n");
        proc_terminate($chromeProcess);
        exit(2);
    }

    $ch = curl_init($baseUrl . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $loginPage = curl_exec($ch);
    $loginStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($loginStatus !== 200 || !is_string($loginPage)) {
        fwrite(STDERR, "GET /login failed: HTTP {$loginStatus}\n");
        @unlink($cookieJar);
        proc_terminate($chromeProcess);
        exit(2);
    }

    if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $loginPage, $m)) {
        fwrite(STDERR, "Could not find _csrf on /login.\n");
        @unlink($cookieJar);
        proc_terminate($chromeProcess);
        exit(2);
    }
    $csrf = $m[1];

    // Step 2: POST /login with credentials + _csrf.
    //
    // The response Set-Cookie header is authoritative for
    // the new session ID. Curl's cookie jar is not trusted:
    // on this Windows build the jar has been observed to
    // hold a stale value that does not match the response
    // Set-Cookie, which makes every admin route redirect to
    // /login. Reading the header directly avoids the class
    // of bug.
    //
    // Also verified here:
    //   - the redirect target is the admin dashboard, not
    //     /login (a failed login redirects back to /login);
    //   - the session file actually exists on disk (a
    //     rotate that fails to persist produces a Set-Cookie
    //     pointing at a missing file).
    $postHeaders = [];
    $ch = curl_init($baseUrl . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            '_csrf'    => $csrf,
            'email'    => $adminEmail,
            'password' => $adminPassword,
        ]),
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$postHeaders) {
            $postHeaders[] = $line;
            return strlen($line);
        },
    ]);
    curl_exec($ch);
    $loginPostStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $loginPostLocation = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    if ($loginPostStatus !== 302) {
        fwrite(STDERR, "POST /login returned HTTP {$loginPostStatus}, expected 302.\n");
        @unlink($cookieJar);
        proc_terminate($chromeProcess);
        exit(2);
    }

    if (!str_contains($loginPostLocation, '/commerce/ca-admin')) {
        fwrite(STDERR, "POST /login redirected to {$loginPostLocation}, not the admin dashboard.\n");
        fwrite(STDERR, "Login credentials or CSRF validation failed.\n");
        @unlink($cookieJar);
        proc_terminate($chromeProcess);
        exit(2);
    }

    // Extract the session ID from the response's Set-Cookie
    // header. If multiple Set-Cookie lines mention ca_session
    // (unusual but possible when regenerate fires alongside a
    // shutdown write), the last one wins - that is the value
    // the server considers current.
    $sessionCookie = null;
    foreach ($postHeaders as $line) {
        if (preg_match('/^Set-Cookie:\s*ca_session=([^;]+)/i', $line, $m)) {
            $sessionCookie = $m[1];
        }
    }

    if ($sessionCookie === null) {
        fwrite(STDERR, "POST /login returned no Set-Cookie for ca_session.\n");
        @unlink($cookieJar);
        proc_terminate($chromeProcess);
        exit(2);
    }

    // Verify the session file exists. A Set-Cookie that
    // points at a missing file is worse than no Set-Cookie:
    // the harness would inject a broken cookie and report
    // every admin route as a violation.
    $savePath = ini_get('session.save_path') ?: sys_get_temp_dir();
    $sessFile = rtrim($savePath, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sessionCookie;
    if (!is_file($sessFile)) {
        fwrite(STDERR, "Session file not written: {$sessFile}\n");
        fwrite(STDERR, "Login succeeded but the session file is missing.\n");
        fwrite(STDERR, "See docs/LOCAL-ENVIRONMENT.md for the WSL-to-Windows\n");
        fwrite(STDERR, "session write notes.\n");
        @unlink($cookieJar);
        proc_terminate($chromeProcess);
        exit(2);
    }

    @unlink($cookieJar);
}

// ---------------------------------------------------------------------
// Connect Chrome via CDP
// ---------------------------------------------------------------------
$http = @file_get_contents("http://127.0.0.1:{$debugPort}/json/list");
$targets = json_decode((string) $http, true);
$pageTarget = null;
foreach ((array) $targets as $t) {
    if (($t['type'] ?? '') === 'page') { $pageTarget = $t; break; }
}

if ($pageTarget === null) {
    fwrite(STDERR, "No page target in Chrome.\n");
    proc_terminate($chromeProcess);
    exit(2);
}

try {
    $cdp = CdpClient::connect((string) $pageTarget['webSocketDebuggerUrl']);
} catch (Throwable $e) {
    fwrite(STDERR, "CDP connect failed: " . $e->getMessage() . "\n");
    proc_terminate($chromeProcess);
    exit(2);
}

// Enable Page + Runtime, and set the session cookie if we have one.
try {
    $cdp->send('Page.enable');
    $cdp->send('Runtime.enable');
    $cdp->send('Network.enable');

    if ($sessionCookie !== null) {
        // url scoping creates a host-only cookie for the
        // base URL, matching the server's Set-Cookie (which
        // has no Domain attribute). domain + path would
        // create a domain-scoped cookie, which is subtly
        // different on the browser side.
        $setResult = $cdp->send('Network.setCookie', [
            'name'     => 'ca_session',
            'value'    => $sessionCookie,
            'url'      => $baseUrl . '/commerce/ca-admin',
            'httpOnly' => true,
            'secure'   => false,
            'sameSite' => 'Lax',
        ]);
        if (($setResult['success'] ?? false) !== true) {
            fwrite(STDERR, "Network.setCookie for ca_session failed.\n");
            $cdp->close();
            proc_terminate($chromeProcess);
            exit(2);
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "CDP setup failed: " . $e->getMessage() . "\n");
    $cdp->close();
    proc_terminate($chromeProcess);
    exit(2);
}

// ---------------------------------------------------------------------
// Navigation helpers
// ---------------------------------------------------------------------
function set_viewport(CdpClient $cdp, int $width): void
{
    $cdp->send('Emulation.setDeviceMetricsOverride', [
        'width'             => $width,
        'height'            => 900,
        'deviceScaleFactor' => 1,
        'mobile'            => false,
    ]);
}

function navigate_and_wait(CdpClient $cdp, string $url, string $expectedPath): array
{
    // Flush any stale events from the previous navigation.
    $cdp->drainEvents('Page.loadEventFired');
    $cdp->drainEvents('Page.frameNavigated');

    $cdp->send('Page.navigate', ['url' => $url]);

    // Wait for load.
    $cdp->waitForEvent('Page.loadEventFired', 20.0);

    // Confirm the final URL matches the requested path. If a
    // login redirect or a 404 landed, abort this navigation.
    $eval = $cdp->send('Runtime.evaluate', [
        'expression'    => 'location.pathname',
        'returnByValue' => true,
    ]);
    $finalPath = (string) ($eval['result']['value'] ?? '');

    // Normalize: requested /cart might arrive as /cart or /cart/
    $expectedNorm = rtrim($expectedPath, '/');
    $finalNorm = rtrim($finalPath, '/');
    if ($expectedNorm === '') $expectedNorm = '/';
    if ($finalNorm === '') $finalNorm = '/';

    if ($expectedNorm !== $finalNorm) {
        return ['error' => "Redirected: {$expectedPath} -> {$finalPath}"];
    }

    return ['ok' => true];
}

function settle(CdpClient $cdp): void
{
    // Poll readyState until complete, then a short wait for
    // fonts and lazy images.
    $deadline = microtime(true) + 5.0;
    while (microtime(true) < $deadline) {
        $eval = $cdp->send('Runtime.evaluate', [
            'expression'    => 'document.readyState',
            'returnByValue' => true,
        ]);
        $state = (string) ($eval['result']['value'] ?? '');
        if ($state === 'complete') {
            break;
        }
        usleep(50_000);
    }
    usleep(200_000);
}

function check_overflow(CdpClient $cdp): array
{
    $expr = <<<'JS'
    (function() {
        var de = document.documentElement;
        return {
            sw: de.scrollWidth,
            cw: de.clientWidth,
            bw: document.body.scrollWidth,
            iw: window.innerWidth,
        };
    })()
    JS;

    $eval = $cdp->send('Runtime.evaluate', [
        'expression'    => $expr,
        'returnByValue' => true,
    ]);
    $value = (array) ($eval['result']['value'] ?? []);
    return [
        'scrollWidth'  => (int) ($value['sw'] ?? 0),
        'clientWidth'  => (int) ($value['cw'] ?? 0),
        'bodyWidth'    => (int) ($value['bw'] ?? 0),
        'innerWidth'   => (int) ($value['iw'] ?? 0),
    ];
}

function is_exempt(array $m): bool
{
    // A 2px tolerance absorbs scrollbar / rounding noise.
    // A known-scrolling container inside the page can push
    // scrollWidth past clientWidth without a body scrollbar;
    // the harness cannot tell those apart from the top-level
    // measurement, so the exemptions are named here and the
    // delta is checked after.
    return ($m['scrollWidth'] - $m['clientWidth']) < 2;
}

// ---------------------------------------------------------------------
// The run
// ---------------------------------------------------------------------
$totalNavigations = count($routes) * count($viewports);
$violations = [];
$harnessErrors = [];

$allRoutesRaw = extract_get_routes($basePath);
$skippedCount = $filterRoute !== null ? 0 : count_skipped($allRoutesRaw, $skipLogin);

echo "Checking rendered layout...\n\n";
echo "  base url:    {$baseUrl}\n";
echo "  routes:      " . count($routes) . " (checked)";
if ($skippedCount > 0) {
    echo ", " . $skippedCount . " (skipped)";
}
echo "\n";
echo "  viewports:   " . implode(', ', $viewports) . "\n";
echo "  navigations: {$totalNavigations}\n";

if ($sessionCookie !== null) {
    echo "  login:       ok (admin session)\n";
} else {
    echo "  login:       skipped (--no-login)\n";
}

echo "\n";

$navIndex = 0;
foreach ($routes as $route) {
    foreach ($viewports as $vp) {
        $navIndex++;
        $url = $baseUrl . $route;

        try {
            set_viewport($cdp, $vp);
            $navResult = navigate_and_wait($cdp, $url, $route);
            if (isset($navResult['error'])) {
                $harnessErrors[] = [
                    'route' => $route,
                    'vp'    => $vp,
                    'error' => $navResult['error'],
                ];
                if ($verbose) {
                    echo "  [ERR]  {$route} @ {$vp}: {$navResult['error']}\n";
                }
                continue;
            }
            settle($cdp);
            $measure = check_overflow($cdp);

            if (!is_exempt($measure)) {
                $violations[] = [
                    'route'       => $route,
                    'viewport'    => $vp,
                    'scrollWidth' => $measure['scrollWidth'],
                    'clientWidth' => $measure['clientWidth'],
                ];
                if ($verbose) {
                    echo "  [FAIL] {$route} @ {$vp}  sw={$measure['scrollWidth']} cw={$measure['clientWidth']}\n";
                }
            } elseif ($verbose) {
                echo "  [ok]   {$route} @ {$vp}\n";
            }
        } catch (Throwable $e) {
            $harnessErrors[] = [
                'route' => $route,
                'vp'    => $vp,
                'error' => $e->getMessage(),
            ];
            if ($verbose) {
                echo "  [ERR]  {$route} @ {$vp}: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Clean up.
try { $cdp->close(); } catch (Throwable $e) { /* ignore */ }
proc_terminate($chromeProcess);
proc_close($chromeProcess);
@rmdir($userDataDir);

// ---------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------
echo "\n";
if ($violations !== []) {
    foreach ($violations as $v) {
        echo "  {$v['route']}  @ {$v['viewport']}   "
            . "scrollWidth {$v['scrollWidth']} > clientWidth {$v['clientWidth']}\n";
    }
    echo "\n";
}
if ($harnessErrors !== []) {
    echo "  Harness errors (" . count($harnessErrors) . "):\n";
    foreach ($harnessErrors as $h) {
        echo "    {$h['route']} @ {$h['vp']}: {$h['error']}\n";
    }
    echo "\n";
}

if ($harnessErrors !== []) {
    echo "FAILED: " . count($harnessErrors) . " harness error(s).\n";
    exit(2);
}

if ($violations === []) {
    echo "OK - no horizontal overflow in {$totalNavigations} navigation(s).\n";
    exit(0);
}

echo "FAILED: " . count($violations) . " violation(s).\n";
exit(1);
