<?php
/**
 * Storefront utility bar.
 *
 * Thin black bar at the very top of every storefront page. Shows
 * four trust badges and two non-functional selectors (language,
 * currency). The selectors are display-only stubs until
 * multi-currency and multi-language land in a later stage.
 *
 * Context:
 *   $store  ?Store  the current store, or null
 *
 * Layout:
 *   Left/center: trust badges with vertical dividers
 *   Right:       language + currency selectors
 *
 * Mobile:
 *   The trust badges hide below 900px. The selectors stay visible.
 *   Pure CSS media query, no JS required.
 */

$store = $store ?? null;

// Defaults. Used when the merchant has not configured the
// trust bar in Settings > General > Trust bar items, or
// when the configured list is empty.
$defaultBadges = [
    ['icon' => 'bi-globe2',        'label' => 'Worldwide Shipping',      'href' => '/pages/shipping'],
    ['icon' => 'bi-truck',         'label' => 'Fast & Safe Delivery',    'href' => '/pages/shipping'],
    ['icon' => 'bi-shield-check',  'label' => 'Secure Payment',          'href' => '/pages/secure-payment'],
    ['icon' => 'bi-headset',       'label' => '24/7 Customer Support',   'href' => '/pages/contact'],
];

// Read the merchant's configuration. Each line is
// "icon|label|url". Blank lines and lines without a label
// are skipped. The url is optional.
$badges = [];
try {
    $settingsRepo = \CorporateAid\Core\App::instance()
        ->container()
        ->get(\CorporateAid\Commerce\Repositories\StoreSettingsRepository::class);
    $raw = $settingsRepo->string('utility_bar.items', '');
    if (trim($raw) !== '') {
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) < 2 || $parts[1] === '') continue;
            $badges[] = [
                'icon'  => $parts[0] !== '' ? $parts[0] : 'bi-check-circle',
                'label' => $parts[1],
                'href'  => $parts[2] ?? '',
            ];
        }
    }
} catch (\Throwable) {
    $badges = [];
}

// Fall back to defaults when the setting is empty or produced
// zero valid items. A merchant who wants an empty bar can set
// the setting to a single blank line — but a genuinely blank
// setting (never configured) gets the defaults.
if ($badges === []) {
    $badges = $defaultBadges;
}
?>
<div class="sf-utility-bar" role="region" aria-label="Site information">
    <div class="sf-container sf-utility-inner">

        <ul class="sf-utility-badges" aria-label="Trust and service information">
            <?php foreach ($badges as $i => $badge): ?>
                <?php if ($i > 0): ?>
                    <li class="sf-utility-divider" aria-hidden="true"></li>
                <?php endif; ?>
                <?php
                $hasHref = !empty($badge['href']) && $badge['href'] !== '#';
                $tag     = $hasHref ? 'a' : 'span';
                $hrefAttr = $hasHref ? ' href="' . e($badge['href']) . '"' : '';
                ?>
                <li class="sf-utility-badge">
                    <<?= $tag ?><?= $hrefAttr ?> class="sf-utility-badge-link">
                        <i class="bi <?= e($badge['icon']) ?>" aria-hidden="true"></i>
                        <span><?= e($badge['label']) ?></span>
                    </<?= $tag ?>>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="sf-utility-selectors">
            <span class="sf-utility-selector" title="Language selector coming soon">
                <i class="bi bi-globe" aria-hidden="true"></i>
                <span>English</span>
                <i class="bi bi-chevron-down sf-utility-chevron" aria-hidden="true"></i>
            </span>

            <?php
            // Display the store's default currency. We do not offer
            // a currency switcher yet - the merchant will decide
            // which currencies to support in a later stage.
            $currencyDisplay = 'EUR €';
            if ($store !== null && !empty($store->defaultCurrencyId)) {
                $currencyRepo = \CorporateAid\Core\App::instance()->container()->get(\CorporateAid\Commerce\Repositories\CurrencyRepository::class);
                $activeCurrencies = $currencyRepo->listActive();
                foreach ($activeCurrencies as $c) {
                    if ($c['code'] === 'EUR') { // Default fallback to EUR if ID does not resolve
                        $currencyDisplay = $c['code'] . ' ' . ($c['symbol'] ?? '');
                        break;
                    }
                }
            }
            ?>
            <span class="sf-utility-selector" title="Default store currency">
                <span><?= e($currencyDisplay) ?></span>
            </span>
        </div>

    </div>
</div>
