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
$hasFlash     = ($flashSuccess !== null || $flashError !== null);

$branding  = function_exists('config') ? (array) config('branding', []) : [];
$brandName = $branding['product_name'] ?? 'Anaya Global';

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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= versioned_asset('bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('fontawesome/css/all.min.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/app.css') ?>" rel="stylesheet">
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

            <?= $content ?? '' ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= asset($script) ?>"></script>
    <?php endforeach; ?>

    <?php if (function_exists('config') && config('app.debug')): ?>
    <script>
    /* Dev-mode error overlay. Only renders when app.debug is on.
       Catches uncaught errors, unhandled promise rejections, and
       explicit console.error calls. Displays them in a dismissible
       corner overlay so client-side issues surface without opening
       DevTools. Zero cost when debug is off. */
    (function () {
        'use strict';
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
    })();
    </script>
    <?php endif; ?>
</body>
</html>
