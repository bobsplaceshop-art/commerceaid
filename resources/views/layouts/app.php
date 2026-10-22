<?php
/**
 * Admin shell layout.
 *
 * Structure: sidebar + topbar + content area. All admin pages use
 * this layout via View::render($view, $data, 'layouts.app').
 *
 * Context (injected by AdminController):
 *   $user          - current User (may be null)
 *   $title         - page title for breadcrumb + browser tab
 *   $setupComplete - bool, controls sidebar lock state
 *   $pageScripts   - optional array of extra JS asset paths
 */
$user          = $user          ?? null;
$setupComplete = $setupComplete ?? true;
$pageScripts   = $pageScripts   ?? [];

$flashSuccess = function_exists('flash') ? flash('success') : null;
$flashError   = function_exists('flash') ? flash('error')   : null;
$flashWarning = function_exists('flash') ? flash('warning') : null;
$hasFlash     = ($flashSuccess !== null || $flashError !== null || $flashWarning !== null);

// Store-scoped brand name. Reads the resolved store's
// name first, falls back to config('branding.product_name')
// when no store is bound. The helper is the single source
// of truth for every branded surface in the admin shell.
$brandName = brand_name($store ?? null);

// Theme: server-side is the source of truth. localStorage may
// override if the user toggled the theme on this device since
// the last login. The pre-paint script below resolves the final
// value before first paint to prevent any flash.
$serverTheme = 'system';
if ($user !== null && !empty($user->themePreference)) {
    $serverTheme = $user->themePreference;
}
if (!in_array($serverTheme, ['dark', 'light', 'system'], true)) {
    $serverTheme = 'system';
}
?>
<!doctype html>
<html lang="en" data-theme="<?= e($serverTheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($brandName) ?><?php if (!empty($title)): ?> - <?= e($title) ?><?php endif; ?></title>
    <?php
    // Same favicon resolution as the storefront. Prevents
    // the browser from requesting /favicon.ico (which has no
    // matching route and would be 404'd by the router).
    $__adminFavicon = function_exists('store_favicon_url')
        ? store_favicon_url()
        : null;
    if ($__adminFavicon === null || $__adminFavicon === '') {
        $__adminFavicon = '/assets/img/pwa/icon-192.png';
    }
    ?>
    <link rel="icon" type="image/png" href="<?= e($__adminFavicon) ?>">
    <link rel="apple-touch-icon" href="<?= e($__adminFavicon) ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= versioned_asset('bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('fontawesome/css/all.min.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/main-v2.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/setup.css') ?>" rel="stylesheet">

    <?php /* Pre-paint theme resolution - runs before stylesheets apply */ ?>
    <script>
    (function () {
        var serverTheme = <?= json_encode($serverTheme, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var stored = null;
        try { stored = localStorage.getItem('ra-theme'); } catch (e) {}
        var theme = (stored === 'dark' || stored === 'light' || stored === 'system') ? stored : serverTheme;
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
</head>
<body>
    <div class="ra-shell">
        <?= view_partial('partials/sidebar', [
            'user'          => $user,
            'setupComplete' => $setupComplete,
            'brandName'     => $brandName,
        ]) ?>

        <?= view_partial('partials/topbar', [
            'user'  => $user,
            'title' => $title ?? null,
        ]) ?>

        <main class="ra-content<?= $hasFlash ? ' has-flash' : '' ?>">
            <?php if ($flashSuccess !== null): ?>
                <div class="ra-alert ra-alert-success"><?= e((string) $flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError !== null): ?>
                <div class="ra-alert ra-alert-error"><?= e((string) $flashError) ?></div>
            <?php endif; ?>
            <?php if ($flashWarning !== null): ?>
                <div class="ra-alert ra-alert-warn"><?= e((string) $flashWarning) ?></div>
            <?php endif; ?>

            <?= $content ?? '' ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/admin-filters.js') ?>"></script>
    <script src="<?= versioned_asset('js/block-media-picker.js') ?>"></script>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= asset($script) ?>"></script>
    <?php endforeach; ?>

    <?php
    // Two conditions for the dev overlay:
    //
    //   1. app.debug is on (production never renders it).
    //   2. The session flag ra_debug_overlay is set. A
    //      developer turns it on by visiting any admin URL
    //      with ?ra-debug=1; ?ra-debug=0 turns it off. The
    //      default is off, which means a merchant never sees
    //      the overlay even if app.debug is accidentally on.
    //
    // The overlay is developer tooling. It must not appear
    // for a customer. The logs screen at
    // /commerce/ca-admin/settings/logs is the merchant-
    // facing surface for the same data.
    $__overlayOn = function_exists('config') && config('app.debug');
    if ($__overlayOn) {
        try {
            $__overlayOn = (bool) ($_SESSION['ra_debug_overlay'] ?? false);
        } catch (\Throwable) {
            $__overlayOn = false;
        }
    }
    ?>
    <?php if ($__overlayOn): ?>
    <script>
    /* Dev-mode error overlay. Only renders when app.debug is on.
       Catches uncaught errors, unhandled promise rejections, and
       explicit console.error calls. Displays them in a dismissible
       corner overlay so client-side issues surface without opening
       DevTools. Zero cost when debug is off. */
    (function () {
        'use strict';

        // Skip the whole overlay below the small-mobile boundary.
        // The CSS also hides #ra-debug-overlay at that width, but
        // returning early here avoids the DOM work and the fetch.
        if (window.innerWidth < 640) return;

        var MAX_ITEMS = 5;
        var TTL_MS = 8000;
        var container = null;

        function ensureContainer() {
            if (container) return container;
            container = document.createElement('div');
            container.id = 'ra-debug-overlay';
            container.setAttribute('style',
                'position:fixed;top:10px;right:10px;z-index:2147483647;' +
                'max-width:420px;font-family:monospace;font-size:12px;'
            );
            document.body.appendChild(container);
            return container;
        }

        function show(kind, message, detail) {
            var c = ensureContainer();
            var item = document.createElement('div');
            item.setAttribute('style',
                'background:' + (kind === 'error' ? '#7f1d1d' : '#78350f') + ';' +
                'color:#fff;padding:8px 12px;margin-bottom:6px;border-radius:4px;' +
                'box-shadow:0 2px 8px rgba(0,0,0,0.4);word-break:break-word;'
            );
            item.textContent = '[' + kind + '] ' + message +
                (detail ? ' - ' + detail : '');
            c.appendChild(item);

            while (c.children.length > MAX_ITEMS) {
                c.removeChild(c.firstChild);
            }

            setTimeout(function () {
                if (item.parentNode) item.parentNode.removeChild(item);
            }, TTL_MS);
        }

        // Expose the display function so the server-side error
        // fetch below can reuse it. The IIFE closes over show();
        // assigning to window is the only way to reach it from
        // the fetch callback.
        window.raDebugShow = show;

        window.addEventListener('error', function (e) {
            show('error', e.message || 'unknown',
                (e.filename || '') + ':' + (e.lineno || ''));
        });

        window.addEventListener('unhandledrejection', function (e) {
            show('error', 'Unhandled promise rejection',
                e.reason ? String(e.reason) : '');
        });

        var origError = console.error;
        console.error = function () {
            try {
                show('error', Array.prototype.map.call(arguments, String).join(' '), '');
            } catch (ignore) {}
            return origError.apply(console, arguments);
        };

        // Server-side exception fetch. The endpoint returns 404
        // when app.debug is off, so this is a no-op in production.
        // In dev, the last 60 seconds of logged exceptions are
        // displayed in the same overlay JS errors use.
        //
        // The endpoint returns raw log lines. Only the JSON
        // portion is extracted for display - the timestamp
        // prefix is redundant with the browser's own console
        // timestamps.
        var DEBUG_ENDPOINT = '/commerce/ca-admin/_debug/recent-errors';
        var debugSince = Math.floor(Date.now() / 1000) - 60;

        function pollDebugEndpoint() {
            fetch(DEBUG_ENDPOINT + '?since=' + debugSince, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.lines || !data.lines.length) return;
                debugSince = Math.floor(Date.now() / 1000);

                data.lines.forEach(function (raw) {
                    // Extract the JSON tail after the
                    // [timestamp] LEVEL: prefix.
                    var idx = raw.indexOf('{');
                    var detail = idx >= 0 ? raw.slice(idx) : raw;
                    try {
                        var parsed = JSON.parse(detail);
                        var msg = (parsed.file || '?') + ':' + (parsed.line || '?') +
                                  ' - ' + (parsed.trace || '').split('\n')[0];
                        show('server', 'exception', msg);
                    } catch (e) {
                        show('server', 'exception', detail);
                    }
                });
            })
            .catch(function () { /* network failure, ignore */ });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', pollDebugEndpoint);
        } else {
            pollDebugEndpoint();
        }
    })();
    </script>
    <?php endif; ?>
</body>
</html>
