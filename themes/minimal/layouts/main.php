<?php
/**
 * Minimal theme layout.
 *
 * Centered logo, no category nav, minimal footer.
 * Feels like a luxury boutique.
 */
$storeName = $store !== null ? $store->name : 'Store';
$flashSuccess = function_exists('flash') ? flash('success') : null;
$flashError   = function_exists('flash') ? flash('error') : null;
$cartCount    = $cartCount ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> - <?= e($storeName) ?></title>
    <?= view_partial('storefront/partials/_head', ['store' => $store]) ?>
    <style>
        body { font-family: var(--sf-font-body, system-ui, sans-serif); background: var(--sf-cream); color: var(--sf-charcoal); }
        .minimal-header { text-align: center; padding: 2rem 1rem; border-bottom: 1px solid var(--sf-border); }
        .minimal-logo img { max-height: 60px; }
        .minimal-logo-text { font-family: var(--sf-font-display, serif); font-size: 1.75rem; font-weight: 400; letter-spacing: 0.15em; text-transform: uppercase; }
        .minimal-nav { padding: 1rem; display: flex; justify-content: center; gap: 2rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; }
        .minimal-nav a { color: var(--sf-charcoal); text-decoration: none; }
        .minimal-nav a:hover { color: var(--sf-gold); }
        .minimal-cart { position: absolute; top: 1.5rem; right: 2rem; font-size: 0.85rem; }
        .minimal-cart a { color: var(--sf-charcoal); text-decoration: none; }
        .minimal-content { max-width: 1400px; margin: 0 auto; padding: 2rem 1rem; }
        .minimal-footer { text-align: center; padding: 3rem 1rem; border-top: 1px solid var(--sf-border); font-size: 0.8rem; color: var(--sf-muted); margin-top: 4rem; }
        .minimal-flash { max-width: 600px; margin: 1rem auto; padding: 0.75rem 1rem; border-radius: 4px; text-align: center; font-size: 0.9rem; }
        .minimal-flash-success { background: #ecfdf5; color: #065f46; }
        .minimal-flash-error { background: #fef2f2; color: #991b1b; }
    </style>
</head>
<body style="position: relative;">
    <?php if (!empty($storeNotice)): ?>
        <div class="sf-store-notice" role="status"><?= e($storeNotice) ?></div>
    <?php endif; ?>

    <div class="minimal-cart">
        <a href="/cart"><i class="bi bi-bag"></i> <?= (int) $cartCount ?></a>
    </div>

    <header class="minimal-header">
        <div class="minimal-logo">
            <?php if ($store !== null && $store->hasLogo()): ?>
                <a href="/"><img src="<?= e(store_logo_url()) ?>" alt="<?= e($storeName) ?>"></a>
            <?php else: ?>
                <a href="/" style="text-decoration: none;"><div class="minimal-logo-text"><?= e($storeName) ?></div></a>
            <?php endif; ?>
        </div>
        <nav class="minimal-nav">
            <a href="/">Home</a>
            <a href="/products">Shop</a>
            <a href="/categories">Categories</a>
            <a href="/account">Account</a>
        </nav>
    </header>

    <?php if ($flashSuccess !== null): ?>
        <div class="minimal-flash minimal-flash-success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError !== null): ?>
        <div class="minimal-flash minimal-flash-error"><?= e($flashError) ?></div>
    <?php endif; ?>

    <main class="minimal-content">
        <?= $content ?>
    </main>

    <footer class="minimal-footer">
        &copy; <?= date('Y') ?> <?= e($storeName) ?>. All rights reserved.
    </footer>
</body>
</html>
