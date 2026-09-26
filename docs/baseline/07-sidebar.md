# 7. Sidebar

Section of: [BASELINE](../BASELINE.md)

## How It Renders

The sidebar is a self-contained partial rendered by the layout:

    <?= view_partial('partials/sidebar', [
        'user'          => $user,
        'setupComplete' => $setupComplete,
    ]) ?>

The partial receives exactly two variables. It reads the current URL
directly from $_SERVER['REQUEST_URI'] to determine the active item.

## The Groups Array

All navigation structure lives in a single $groups array at the top
of resources/views/partials/sidebar.php. Each group looks like this:

    [
        'id'    => 'sales',
        'label' => 'Sales',
        'items' => [
            ['label' => 'Orders',           'href' => '/commerce/ca-admin/orders',           'icon' => 'bi-receipt',           'locked' => true],
            ['label' => 'Customer Groups',  'href' => '/commerce/ca-admin/customer-groups',  'icon' => 'bi-people-fill'],
        ],
    ],

## Item Shape

    'label'      - string, the visible text
    'href'       - string, the path to link to
    'icon'       - string, a Bootstrap Icon class (bi-*)
    'permission' - optional string, a permission slug
    'locked'     - optional bool, greys the item until setup completes

## Group Shape

    'id'    - string, unique key used for localStorage persistence
    'label' - string or null. Null renders items flat with no toggle.
    'items' - array of items

## Adding a New Item

Open resources/views/partials/sidebar.php. Find the group where the
item belongs. Add one line to its items array:

    ['label' => 'Customer Approvals', 'href' => '/commerce/ca-admin/customer-approvals', 'icon' => 'bi-check2-square'],

That is the entire change. No CSS, no JS, no other file.

## Adding a New Group

Add a new entry to $groups:

    [
        'id'    => 'reports',
        'label' => 'Reports',
        'items' => [
            ['label' => 'Sales Report', 'href' => '/commerce/ca-admin/reports/sales', 'icon' => 'bi-graph-up'],
        ],
    ],

The group renders as a collapsible section automatically. No JS or
CSS change needed.

## How Active Detection Works

The $isActive closure prefix-matches the current URL:

    $isActive = function (string $href) use ($currentPath): string {
        $href = rtrim($href, '/');
        $cur  = rtrim($currentPath, '/');
        if ($href === '') return $cur === '' ? 'is-active' : '';
        return ($cur === $href || str_starts_with($cur, $href . '/')) ? 'is-active' : '';
    };

So /commerce/ca-admin/customer-approvals matches:

    /commerce/ca-admin/customer-approvals
    /commerce/ca-admin/customer-approvals/42
    /commerce/ca-admin/customer-approvals/42/edit

But not:

    /commerce/ca-admin/customer-approvals-foo

The trailing slash check prevents partial-prefix false positives.

## How Active Group Expansion Works

$isGroupActive checks if any item in the group is active:

    $isGroupActive = function (array $group) use ($isActive): bool {
        foreach ($group['items'] as $item) {
            if ($isActive($item['href']) !== '') return true;
        }
        return false;
    };

If true, the group renders with class is-expanded and
data-ra-nav-active="1".

## State Persistence

The sidebar's JS reads from localStorage['ra-nav-groups']. The shape
is:

    { "business": true, "catalog": false, "sales": true }

The applyState() function has a three-tier priority:

1. If the group is active (data-ra-nav-active="1"), force expand.
   Never persist this as a user preference.
2. Otherwise, apply the saved preference if present.
3. Otherwise, leave the server-rendered default untouched.

An active group cannot be hidden by stale localStorage. This was
fixed in Stage 007A after we hit the "I collapsed Sales once and now
I never see Customer Groups" bug.

## Permission Filtering

$canSee filters items by permission:

    $canSee = function (array $item) use ($user): bool {
        if (!isset($item['permission'])) return true;
        return $user !== null && $user->can($item['permission']);
    };

Items without a permission key are visible to everyone. Items with
a permission key require the current user to have it.

## Locked Items

An item with 'locked' => true renders differently when
$setupComplete is false:

    <span class="ra-nav-item is-locked" title="Complete business setup first" aria-disabled="true">
        <i class="bi bi-box-seam" aria-hidden="true"></i>
        <span>Products</span>
        <i class="bi bi-lock-fill ms-auto" style="font-size:.75rem;" aria-hidden="true"></i>
    </span>

The item is not a link. It shows a lock icon and a title tooltip.
This prevents dead-end clicks to unbuilt routes during initial setup.

## Rendering

The $renderNavItem closure produces the HTML. It returns a string
so it can be concatenated into the group loop. Every link sets
aria-current="page" when active.

## The Footer

Below the nav, if $user is not null, the sidebar renders a small
footer card with the user's initial, name, and first role slug. It
is not a link - the full user menu lives in the topbar.
