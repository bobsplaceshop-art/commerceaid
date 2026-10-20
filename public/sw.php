<?php
/**
 * Service worker, served through PHP so the cache version
 * auto-bumps on every deploy.
 *
 * The browser registers /sw.js. Apache rewrites /sw.js to
 * this file. This file reads sw.js from disk, replaces
 * __COMMIT__ with the current commit hash (or a fallback
 * version token), and serves the result as application/javascript.
 *
 * Why this exists:
 *
 *   The service worker caches HTML, CSS, JS, and fonts in
 *   the browser. When a deploy changes any of those, the
 *   cached version is stale. Bumping CACHE_VERSION in sw.js
 *   invalidates the cache on the next page load - but only
 *   if the browser sees a different sw.js. This file makes
 *   the served sw.js different on every commit without
 *   editing the source.
 *
 * Version source (in priority order):
 *
 *   1. storage/sw-version.txt. Written by the deploy or
 *      by a one-time CLI command. Works on shared hosting
 *      that has no git.
 *
 *   2. .git/HEAD. The current commit hash. Works in dev
 *      and on any host that pulls via git.
 *
 *   3. A date-based fallback. Last resort; still changes
 *      once per day.
 *
 * Cache-Control is set to no-cache so the browser always
 * revalidates the script. The script is ~7 KB. Revalidating
 * is cheap.
 */

declare(strict_types=1);

function current_version(): string
{
    $root = dirname(__DIR__);

    $versionFile = $root . '/storage/sw-version.txt';
    if (is_file($versionFile)) {
        $v = trim((string) @file_get_contents($versionFile));
        if ($v !== '') {
            return $v;
        }
    }

    $headFile = $root . '/.git/HEAD';
    if (is_file($headFile)) {
        $head = trim((string) @file_get_contents($headFile));
        if (str_starts_with($head, 'ref: ')) {
            $refPath = $root . '/.git/' . substr($head, 5);
            if (is_file($refPath)) {
                $sha = trim((string) @file_get_contents($refPath));
                if ($sha !== '') {
                    return substr($sha, 0, 7);
                }
            }
        } elseif ($head !== '') {
            return substr($head, 0, 7);
        }
    }

    return 'fallback-' . date('Ymd');
}

$swPath = __DIR__ . '/sw.js';
if (!is_file($swPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Service worker source not found.';
    exit;
}

$body = (string) file_get_contents($swPath);
$body = str_replace('__COMMIT__', current_version(), $body);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Service-Worker-Allowed: /');
echo $body;
