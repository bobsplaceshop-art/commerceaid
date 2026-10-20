<?php
$flashSuccess = function_exists('flash') ? flash('success') : null;
$flashError   = function_exists('flash') ? flash('error') : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> - <?= e($store->name ?? 'Store') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= versioned_asset('bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('fontawesome/css/all.min.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-home.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-catalog.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-product.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-cart.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-checkout.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-auth.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-forms.css') ?>" rel="stylesheet">
    <link href="/themes/minimal/css/theme-v2.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body>

<?php if (!empty($storeNotice)): ?>
    <div class="sf-store-notice" role="status"><?= e($storeNotice) ?></div>
<?php endif; ?>

<?= view_partial('storefront/partials/utility-bar', ['store' => $store ?? null]) ?>
<?= view_partial('storefront/partials/header', [
    'store'         => $store ?? null,
    'brandName'     => $store->name ?? 'Store',
    'cartCount'     => $cartCount ?? 0,
    'wishlistCount' => count($wishlistProductIds ?? []),
    'navCategories' => $navCategories ?? [],
]) ?>

<?php if ($flashSuccess !== null): ?><div class="minimal-flash minimal-flash-success"><?= e($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError   !== null): ?><div class="minimal-flash minimal-flash-error"><?= e($flashError) ?></div><?php endif; ?>

<main class="minimal-content" style="max-width:1400px; margin:0 auto; padding:2rem 1rem;">
    <?= $content ?>
</main>

<?= view_partial('storefront/partials/footer', ['store' => $store ?? null]) ?>

<?php if (!empty($cookieBannerEnabled)): ?>
    <?= view_partial('storefront/partials/_cookie-banner') ?>
<?php endif; ?>

</body>
</html>
