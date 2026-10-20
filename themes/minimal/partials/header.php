<?php
$store = $store ?? null;
$storeName = $store !== null ? $store->name : 'Store';
$cartCount = $cartCount ?? 0;
$navCategories = $navCategories ?? [];
?>
<header class="minimal-header">
    <div class="minimal-header-inner">
        <a href="/cart" class="minimal-header-link">Cart (<?= (int) $cartCount ?>)</a>

        <a href="/" class="minimal-header-logo">
            <?php if ($store !== null && $store->hasLogo()): ?>
                <img src="<?= e(store_logo_url()) ?>" alt="<?= e($storeName) ?>">
            <?php else: ?>
                <span class="minimal-header-logo-text"><?= e($storeName) ?></span>
            <?php endif; ?>
        </a>

        <a href="/account" class="minimal-header-link">Account</a>
    </div>
</header>

<nav class="minimal-nav">
    <ul class="minimal-nav-list">
        <li><a href="/">Home</a></li>
        <li><a href="/products">Shop</a></li>
        <?php if (!empty($navCategories)): ?>
            <?php foreach (array_slice($navCategories, 0, 4) as $__cat): ?>
                <li><a href="/categories/<?= e($__cat->slug) ?>"><?= e($__cat->name) ?></a></li>
            <?php endforeach; ?>
        <?php else: ?>
            <li><a href="/categories">Categories</a></li>
        <?php endif; ?>
        <li><a href="/cart">Cart</a></li>
    </ul>
</nav>
