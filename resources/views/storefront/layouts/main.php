<?php
/**
 * Storefront layout.
 *
 * The public-facing shell. Distinct from layouts/app.php which
 * serves the admin. Same View pipeline, different chrome.
 *
 * Context (from StorefrontController):
 *   $store   - the resolved store, or null
 *   $title   - page title
 *   $content - the rendered view
 *
 * Context (added by this layout):
 *   $brandName       - the display name for the site
 *   $flashSuccess    - success flash message, or null
 *   $flashError      - error flash message, or null
 *
 * Flash handling: same pattern as the admin layout. Layout reads
 * flash via the global flash() helper, which consumes on read.
 * Views never touch flash themselves.
 *
 * Theme: the storefront is always light. No data-theme attribute.
 * prefers-color-scheme is honored at the CSS level if we ever add
 * a dark palette. The admin dark mode toggle does not affect the
 * public site.
 */

$store   = $store   ?? null;
$title   = $title   ?? '';
$content = $content ?? '';

$flashSuccess = function_exists('flash') ? flash('success') : null;
$flashError   = function_exists('flash') ? flash('error')   : null;
$hasFlash     = ($flashSuccess !== null || $flashError !== null);

$branding  = function_exists('config') ? (array) config('branding', []) : [];
$configName = $branding['product_name'] ?? 'Anaya Global';

// Prefer the store's name when available. Fall back to config.
$brandName = ($store !== null && !empty($store->name))
    ? (string) $store->name
    : $configName;

$pageTitle = $title !== '' ? ($title . ' - ' . $brandName) : $brandName;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/storefront.css') ?>" rel="stylesheet">
</head>
<body class="sf-body">

    <?= view_partial('storefront/partials/header', [
        'store'     => $store,
        'brandName' => $brandName,
    ]) ?>

    <?php if ($hasFlash): ?>
        <div class="sf-flash-wrap">
            <?php if ($flashSuccess !== null): ?>
                <div class="sf-alert sf-alert-success"><?= e((string) $flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError !== null): ?>
                <div class="sf-alert sf-alert-error"><?= e((string) $flashError) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <main class="sf-main">
        <?= $content ?>
    </main>

    <?= view_partial('storefront/partials/footer', [
        'store'     => $store,
        'brandName' => $brandName,
    ]) ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
