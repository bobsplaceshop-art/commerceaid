<?php
/**
 * Account sidebar. Renders the grouped nav for /account/*.
 *
 * Structure mirrors the admin sidebar partial. Same patterns:
 *   - data-sf-account-group     unique id per group
 *   - data-sf-account-active    "1" when the current path is in the group
 *   - data-sf-account-toggle    click target for expand/collapse
 *   - localStorage key:         sf-account-nav
 *
 * The active group is never hidden. Manual collapse of a
 * non-active group persists across navigations.
 *
 * Context:
 *   $currentPath    string
 *   $claimableCount int
 */
$currentPath        = $currentPath        ?? '/';
$claimableCount     = (int) ($claimableCount     ?? 0);
$unreadMessageCount = (int) ($unreadMessageCount ?? 0);

/**
 * Return true when $href matches the current path.
 *
 * Exact match for the dashboard, prefix match for detail routes
 * (orders/{id}, addresses/{id}/edit). The exact-match exception
 * prevents /account from highlighting on every account page -
 * the same bug the admin sidebar had.
 */
$isActive = function (string $href) use ($currentPath): bool {
    if ($href === '#' || $href === '') {
        return false;
    }
    if ($href === '/account') {
        return $currentPath === '/account';
    }
    return $currentPath === $href
        || str_starts_with($currentPath, $href . '/');
};

/**
 * Item shape:
 *   ['label' => string, 'href' => string, 'icon' => string,
 *    'badge' => int, 'soon' => bool]
 *
 * Group shape:
 *   ['id' => string, 'label' => string|null, 'items' => [item, ...]]
 */
$groups = [
    [
        'id'    => 'account',
        'label' => 'My Account',
        'items' => [
            ['label' => 'Dashboard', 'href' => '/account',          'icon' => 'bi-house'],
            ['label' => 'Addresses', 'href' => '/account/addresses','icon' => 'bi-geo-alt'],
            ['label' => 'Password',  'href' => '/account/password', 'icon' => 'bi-lock'],
        ],
    ],
    [
        'id'    => 'orders',
        'label' => 'My Orders',
        'items' => [
            ['label' => 'Orders', 'href' => '/account/orders', 'icon' => 'bi-receipt'],
            [
                'label' => 'Claims',
                'href'  => '/account/claims',
                'icon'  => 'bi-link-45deg',
                'badge' => $claimableCount,
            ],
        ],
    ],
    [
        'id'    => 'returns',
        'label' => 'Returns',
        'items' => [
            ['label' => 'Returns', 'href' => '/account/returns', 'icon' => 'bi-arrow-return-left'],
        ],
    ],
    [
        'id'    => 'wishlist',
        'label' => 'Wishlist',
        'items' => [
            ['label' => 'Wishlist', 'href' => '/account/wishlist', 'icon' => 'bi-heart'],
        ],
    ],
    [
        'id'    => 'recent',
        'label' => 'Recently Viewed',
        'items' => [
            ['label' => 'Recently Viewed', 'href' => '/account/recent', 'icon' => 'bi-clock-history'],
        ],
    ],
    [
        'id'    => 'messages',
        'label' => 'Messages',
        'items' => [
            [
                'label' => 'Messages',
                'href'  => '/account/messages',
                'icon'  => 'bi-envelope',
                'badge' => $unreadMessageCount,
            ],
        ],
    ],
];

$isGroupActive = function (array $group) use ($isActive): bool {
    foreach ($group['items'] as $item) {
        if ($isActive((string) $item['href'])) {
            return true;
        }
    }
    return false;
};
?>
<nav class="sf-account-nav" aria-label="Account navigation">
    <ul class="sf-account-nav-list">
        <?php foreach ($groups as $group): ?>
            <?php
            $groupActive = $isGroupActive($group);
            // Skip groups with no visible items entirely.
            if ($group['items'] === []) {
                continue;
            }
            ?>
            <li class="sf-account-nav-group<?= $groupActive ? ' is-expanded' : '' ?>"
                data-sf-account-group="<?= e($group['id']) ?>"
                data-sf-account-active="<?= $groupActive ? '1' : '0' ?>">

                <?php if ($group['label'] !== null): ?>
                    <button type="button"
                            class="sf-account-nav-toggle"
                            data-sf-account-toggle="<?= e($group['id']) ?>"
                            aria-expanded="<?= $groupActive ? 'true' : 'false' ?>">
                        <span class="sf-account-nav-label"><?= e($group['label']) ?></span>
                        <i class="bi bi-chevron-down sf-account-nav-chevron" aria-hidden="true"></i>
                    </button>
                <?php endif; ?>

                <ul class="sf-account-nav-items" id="sf-account-items-<?= e($group['id']) ?>">
                    <?php foreach ($group['items'] as $item): ?>
                        <?php
                        $href   = (string) $item['href'];
                        $label  = (string) $item['label'];
                        $icon   = (string) ($item['icon'] ?? 'bi-dot');
                        $badge  = (int) ($item['badge'] ?? 0);
                        $soon   = (bool) ($item['soon'] ?? false);
                        $active = $isActive($href);
                        ?>
                        <li class="sf-account-nav-item">
                            <?php if ($soon): ?>
                                <span class="sf-account-nav-link is-soon"
                                      title="Coming soon"
                                      aria-disabled="true">
                                    <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                                    <span><?= e($label) ?></span>
                                    <span class="sf-account-nav-soon">Soon</span>
                                </span>
                            <?php else: ?>
                                <a href="<?= e($href) ?>"
                                   class="sf-account-nav-link<?= $active ? ' is-active' : '' ?>"
                                   <?= $active ? 'aria-current="page"' : '' ?>>
                                    <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                                    <span><?= e($label) ?></span>
                                    <?php if ($badge > 0): ?>
                                        <span class="sf-account-nav-badge"><?= $badge ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<script>
/**
 * Account sidebar behavior.
 *
 * Same three-tier priority as the admin sidebar:
 *   1. The active group is forced expanded (never persisted).
 *   2. User preference from localStorage applies to non-active groups.
 *   3. Server default (expanded if active, collapsed otherwise).
 *
 * localStorage key is separate from the admin sidebar's so the
 * two never collide.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'sf-account-nav';

    function readState() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {};
        } catch (err) {
            return {};
        }
    }

    function writeState(state) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (err) {
            // Ignore storage errors (private mode, quota).
        }
    }

    function applyState() {
        var state = readState();
        var groups = document.querySelectorAll('[data-sf-account-group]');

        groups.forEach(function (group) {
            var id     = group.getAttribute('data-sf-account-group');
            var active = group.getAttribute('data-sf-account-active') === '1';
            var toggle = group.querySelector('[data-sf-account-toggle]');

            if (active) {
                group.classList.add('is-expanded');
                if (toggle) toggle.setAttribute('aria-expanded', 'true');
                return;
            }

            if (Object.prototype.hasOwnProperty.call(state, id)) {
                if (state[id]) {
                    group.classList.add('is-expanded');
                    if (toggle) toggle.setAttribute('aria-expanded', 'true');
                } else {
                    group.classList.remove('is-expanded');
                    if (toggle) toggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }

    function attachToggles() {
        document.querySelectorAll('[data-sf-account-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-sf-account-toggle');
                var group = btn.closest('[data-sf-account-group]');
                if (!group) return;

                var isExpanded = group.classList.toggle('is-expanded');
                btn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');

                // Do not persist the state of an active group.
                if (group.getAttribute('data-sf-account-active') === '1') {
                    return;
                }

                var state = readState();
                state[id] = isExpanded;
                writeState(state);
            });
        });
    }

    function init() {
        applyState();
        attachToggles();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
