<?php
/**
 * Shared storefront scripts.
 *
 * Rendered by both the base layout (resources/views/storefront/
 * layouts/main.php) and the active theme layout
 * (themes/modern/layouts/main.php). Keeping the script tags in
 * one file means a new script is added here and both layouts
 * get it, instead of being added to each layout separately
 * (Pattern 50 — two layouts, one render path).
 *
 * Context (from the caller):
 *   $store               ?Store
 *   $cookieBannerEnabled bool
 *   $popupEnabled        bool
 */
?>
<script src="<?= versioned_asset('js/storefront-hero.js') ?>" defer></script>
<script src="<?= versioned_asset('js/wishlist.js') ?>" defer></script>
<script src="<?= versioned_asset('js/pwa-install.js') ?>" defer></script>
<script src="<?= versioned_asset('js/storefront-forms.js') ?>" defer></script>
<script src="<?= versioned_asset('js/country-select.js') ?>" defer></script>
<script src="<?= versioned_asset('js/search-autocomplete-v3.js') ?>" defer></script>

<?php if (!empty($cookieBannerEnabled)): ?>
    <?= view_partial('storefront.partials._cookie-banner', ['store' => $store]) ?>
    <link href="<?= versioned_asset('css/storefront-cookie-banner.css') ?>" rel="stylesheet">
    <script src="<?= versioned_asset('js/cookie-consent.js') ?>" defer></script>
<?php endif; ?>

<?php if (!empty($popupEnabled)): ?>
    <?= view_partial('storefront.partials._popup-modal', [
        'settings' => app(\CorporateAid\Commerce\Repositories\StoreSettingsRepository::class),
    ]) ?>
    <script src="<?= versioned_asset('js/storefront-popup.js') ?>" defer></script>
<?php endif; ?>

<script>
// Service worker registration. Silent on failure - the
// page works fine without a service worker.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
}
</script>
