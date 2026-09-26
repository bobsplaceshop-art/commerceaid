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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/setup.css') ?>" rel="stylesheet">

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
</body>
</html>
