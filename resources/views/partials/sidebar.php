<?php
/**
 * Admin sidebar.
 *
 * Grouped navigation with collapsible sections. Each group can be
 * expanded or collapsed. State persistence priorities:
 *
 *   1. Active group (contains the current page) - always expanded
 *   2. User preference in localStorage - applies to non-active groups
 *   3. Server default - expanded if active, collapsed otherwise
 *
 * Design rules:
 *   - The active group is never hidden. If you're on a page inside
 *     a group, that group is always visible.
 *   - Manual collapse of a non-active group persists across navigations.
 *   - Groups with no label render flat (no toggle).
 *   - Groups with no visible items (all filtered by permission) skip
 *     rendering entirely.
 *   - The sidebar scroll container is scrolled so the active item is
 *     visible on load, even when the active group sits below the fold.
 *
 * Context:
 *   $user          - current User (may be null)
 *   $setupComplete - bool, controls lock state for unready items
 */

$user = $user ?? null;
$setupComplete = $setupComplete ?? true;

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/**
 * Return 'is-active' if $href matches the current path.
 *
 * Prefix-aware: /commerce/ca-admin/products matches
 * /commerce/ca-admin/products/123 too. This is what makes the
 * nested detail routes highlight the parent list item.
 *
 * Exception: /commerce/ca-admin (Dashboard) requires an EXACT
 * match. Its href is a strict prefix of every other admin route,
 * so prefix matching would mark Dashboard active on every admin
 * page. Every other href continues to prefix-match.
 */
$isActive = function (string $href) use ($currentPath): string {
    $href = rtrim($href, '/');
    $cur  = rtrim($currentPath, '/');

    if ($href === '') {
        return $cur === '' ? 'is-active' : '';
    }

    if ($href === '/commerce/ca-admin') {
        return $cur === $href ? 'is-active' : '';
    }

    return ($cur === $href || str_starts_with($cur, $href . '/')) ? 'is-active' : '';
};

/**
 * Navigation structure.
 *
 * Each group: [
 *   'id'    => unique string used for localStorage key,
 *   'label' => string|null (null = ungrouped, renders flat),
 *   'items' => [item, ...],
 * ]
 *
 * Each item: [
 *   'label' => string,
 *   'href'  => string,
 *   'icon'  => Bootstrap Icon class,
 *   'permission' => optional permission slug,
 *   'locked'     => optional bool (greyed until setup complete),
 * ]
 */
$groups = [
    [
        'id'    => 'main',
        'label' => null,
        'items' => [
            ['label' => 'Dashboard', 'href' => '/commerce/ca-admin', 'icon' => 'bi-speedometer2'],
        ],
    ],
    [
        'id'    => 'business',
        'label' => 'Business',
        'items' => [
            ['label' => 'Setup', 'href' => '/commerce/ca-admin/setup', 'icon' => 'bi-sliders'],
        ],
    ],
    [
        'id'    => 'catalog',
        'label' => 'Catalog',
        'items' => [
            ['label' => 'Products',   'href' => '/commerce/ca-admin/products',   'icon' => 'bi-box-seam'],
            ['label' => 'Categories', 'href' => '/commerce/ca-admin/categories', 'icon' => 'bi-diagram-3'],
            ['label' => 'Brands',     'href' => '/commerce/ca-admin/brands',     'icon' => 'bi-bookmark-star'],
            ['label' => 'Inventory',  'href' => '/commerce/ca-admin/inventory',  'icon' => 'bi-boxes'],
            ['label' => 'Attributes', 'href' => '/commerce/ca-admin/attributes', 'icon' => 'bi-input-cursor-text'],
        ],
    ],
    [
        'id'    => 'sales',
        'label' => 'Sales',
        'items' => [
            ['label' => 'Orders',              'href' => '/commerce/ca-admin/orders',              'icon' => 'bi-receipt',           'locked' => true],
            ['label' => 'Customers',           'href' => '/commerce/ca-admin/customers',           'icon' => 'bi-people',            'locked' => true],
            ['label' => 'Customer Groups',     'href' => '/commerce/ca-admin/customer-groups',     'icon' => 'bi-people-fill'],
            ['label' => 'Customer Approvals',  'href' => '/commerce/ca-admin/customer-approvals',  'icon' => 'bi-check2-square'],
            ['label' => 'Coupons',             'href' => '/commerce/ca-admin/coupons',             'icon' => 'bi-ticket-perforated', 'locked' => true],
        ],
    ],
    [
        'id'    => 'settings',
        'label' => 'Settings',
        'items' => [
            ['label' => 'Store Settings', 'href' => '/commerce/ca-admin/settings', 'icon' => 'bi-gear',         'permission' => 'core.settings.manage'],
            ['label' => 'Users',          'href' => '/commerce/ca-admin/users',    'icon' => 'bi-person-badge', 'permission' => 'core.users.view'],
            ['label' => 'Roles',          'href' => '/commerce/ca-admin/roles',    'icon' => 'bi-shield-lock',  'permission' => 'core.settings.manage'],
        ],
    ],
];

/** Should this item be visible to the current user? */
$canSee = function (array $item) use ($user): bool {
    if (!isset($item['permission'])) return true;
    return $user !== null && $user->can($item['permission']);
};

/** Does this group contain the currently-active page? */
$isGroupActive = function (array $group) use ($isActive): bool {
    foreach ($group['items'] as $item) {
        if ($isActive($item['href']) !== '') return true;
    }
    return false;
};

/**
 * Render one nav item. Handles active state, locked state, and icon.
 * Returns HTML as a string so it can be called from the group loop.
 */
$renderNavItem = function (array $item, callable $isActive, bool $setupComplete): string {
    $href = (string) $item['href'];
    $icon = (string) $item['icon'];
    $label = (string) $item['label'];
    $isLocked = !empty($item['locked']) && !$setupComplete;
    $active = $isActive($href);

    if ($isLocked) {
        return sprintf(
            '<span class="ra-nav-item is-locked" title="Complete business setup first" aria-disabled="true">'
            . '<i class="bi %s" aria-hidden="true"></i>'
            . '<span>%s</span>'
            . '<i class="bi bi-lock-fill ms-auto" style="font-size:.75rem;" aria-hidden="true"></i>'
            . '</span>',
            e($icon),
            e($label)
        );
    }

    $ariaCurrent = ($active !== '') ? ' aria-current="page"' : '';
    return sprintf(
        '<a href="%s" class="ra-nav-item %s"%s>'
        . '<i class="bi %s" aria-hidden="true"></i>'
        . '<span>%s</span>'
        . '</a>',
        e($href),
        $active,
        $ariaCurrent,
        e($icon),
        e($label)
    );
};
?>
<aside class="ra-sidebar" id="ra-sidebar">
    <a href="/commerce/ca-admin" class="ra-brand">
        <span class="ra-brand-mark"></span>
        <span>Anaya Global</span>
    </a>

    <nav class="ra-nav">
        <?php foreach ($groups as $group): ?>
            <?php
            $visibleItems = array_values(array_filter($group['items'], $canSee));
            if (empty($visibleItems)) continue;

            $hasLabel    = $group['label'] !== null;
            $groupActive = $hasLabel && $isGroupActive($group);
            ?>

            <?php if (!$hasLabel): ?>
                <!-- Ungrouped: render items flat, no toggle, no wrapper label -->
                <div class="ra-nav-group">
                    <?php foreach ($visibleItems as $item): ?>
                        <?= $renderNavItem($item, $isActive, $setupComplete) ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Grouped: collapsible section -->
                <div class="ra-nav-group<?= $groupActive ? ' is-expanded' : '' ?>"
                     data-ra-nav-group="<?= e($group['id']) ?>"
                     data-ra-nav-active="<?= $groupActive ? '1' : '0' ?>">

                    <button type="button"
                            class="ra-nav-group-toggle"
                            data-ra-nav-toggle="<?= e($group['id']) ?>"
                            aria-expanded="<?= $groupActive ? 'true' : 'false' ?>"
                            aria-controls="ra-nav-items-<?= e($group['id']) ?>">
                        <span class="ra-nav-group-label"><?= e($group['label']) ?></span>
                        <i class="bi bi-chevron-down ra-nav-group-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="ra-nav-group-items" id="ra-nav-items-<?= e($group['id']) ?>">
                        <?php foreach ($visibleItems as $item): ?>
                            <?= $renderNavItem($item, $isActive, $setupComplete) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endforeach; ?>
    </nav>

    <?php if ($user !== null): ?>
    <div class="ra-nav-footer">
        <div class="d-flex align-items-center gap-2 px-2 py-1">
            <div class="ra-avatar"><?= e(strtoupper(substr($user->name, 0, 1))) ?></div>
            <div class="flex-grow-1" style="min-width:0;">
                <div class="small text-truncate"><?= e($user->name) ?></div>
                <div class="muted" style="font-size:.72rem;"><?= e($user->roleSlugs[0] ?? '') ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</aside>

<script>
(function () {
    'use strict';

    var STORAGE_KEY = 'ra-nav-groups';

    /**
     * Read the persisted state. Shape: { 'business': true, 'catalog': false }.
     * Corrupt values return an empty object.
     */
    function readState() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return {};
            var parsed = JSON.parse(raw);
            return (parsed && typeof parsed === 'object') ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function writeState(state) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {}
    }

    /**
     * Apply persisted state to every group with a toggle.
     *
     * PRIORITY RULES:
     *   1. If the group contains the active page (data-ra-nav-active="1"),
     *      force it expanded. Never persist this as a user preference.
     *   2. Otherwise, apply the saved preference if present.
     *   3. Otherwise, leave the server-rendered default untouched.
     */
    function applyState() {
        var state = readState();

        document.querySelectorAll('[data-ra-nav-group]').forEach(function (group) {
            var isActiveGroup = group.getAttribute('data-ra-nav-active') === '1';

            // Rule 1: active groups are always expanded
            if (isActiveGroup) {
                group.classList.add('is-expanded');
                var activeBtn = group.querySelector('[data-ra-nav-toggle]');
                if (activeBtn) activeBtn.setAttribute('aria-expanded', 'true');
                return;
            }

            // Rule 2 & 3: apply saved preference or leave server default
            var id = group.getAttribute('data-ra-nav-group');
            if (state[id] === undefined) return;

            var expanded = !!state[id];
            group.classList.toggle('is-expanded', expanded);
            var btn = group.querySelector('[data-ra-nav-toggle]');
            if (btn) btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    }

    /**
     * Attach click handlers. Active groups can still be manually collapsed
     * for the current page view, but the preference will not persist (see
     * applyState's Rule 1 which forces them back open on next load).
     */
    function wireToggles() {
        document.querySelectorAll('[data-ra-nav-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var group = btn.closest('.ra-nav-group');
                if (!group) return;
                var id = group.getAttribute('data-ra-nav-group');
                if (!id) return;

                var expanded = group.classList.toggle('is-expanded');
                btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');

                // Rule 1: do not persist the manual collapse of an active group
                if (group.getAttribute('data-ra-nav-active') === '1') {
                    return;
                }

                var state = readState();
                state[id] = expanded;
                writeState(state);
            });
        });
    }

    /**
     * Scroll the active nav item into view.
     *
     * The sidebar's scroll container resets to scrollTop=0 on every
     * page load. When the active item sits below the fold (e.g. in
     * the Sales or Settings group on a short viewport), it is
     * invisible even though its parent group was auto-expanded.
     *
     * Only one item should be active now (the /commerce/ca-admin
     * exact-match guard in the PHP prevents Dashboard from being
     * marked active on every page).
     *
     * block: 'nearest' scrolls the minimum amount needed. If the
     * item is already visible, this is a no-op.
     */
    function scrollActiveIntoView() {
        var active = document.querySelector('.ra-nav-item.is-active');
        if (!active) return;
        try {
            active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        } catch (e) {
            // Older browsers: do nothing rather than jump the whole page.
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyState();
            wireToggles();
            scrollActiveIntoView();
        });
    } else {
        applyState();
        wireToggles();
        scrollActiveIntoView();
    }
})();
</script>
