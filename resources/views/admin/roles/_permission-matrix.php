<?php
/**
 * Permission matrix.
 *
 * Included by admin/roles/create.php and admin/roles/edit.php.
 *
 * Context:
 *   $permissionMap array<string,array<int,array<string,mixed>>>
 *                  grouped by module. From
 *                  RoleRepository::permissionsGroupedByModule().
 *   $assignedIds   array<int,int>  permission IDs currently checked.
 *                  Empty on create.
 *   $readOnly      bool            true for is_system roles: every
 *                  checkbox is disabled. Defaults to false.
 *
 * Every permission id is unique. The form submits an array
 * named permissions[]. The controller reads it via
 * Request::input('permissions', []).
 */
$permissionMap = $permissionMap ?? [];
$assignedIds   = $assignedIds   ?? [];
$readOnly      = $readOnly      ?? false;

$assignedSet = array_fill_keys(array_map('intval', $assignedIds), true);

$moduleLabels = [
    'commerce'  => 'Store',
    'catalog'   => 'Catalog',
    'inventory' => 'Inventory',
    'orders'    => 'Orders',
    'customers' => 'Customers',
    'groups'    => 'Customer Groups',
    'approvals' => 'Customer Approvals',
    'media'     => 'Media Library',
    'review'    => 'Product Reviews',
    'legal'     => 'Legal Pages',
    'core'      => 'Platform',
];

// Render modules in this order. Unknown modules go last,
// alphabetically.
$moduleOrder = array_keys($moduleLabels);
$order       = array_flip($moduleOrder);

$mapKeys = array_keys($permissionMap);
usort($mapKeys, function ($a, $b) use ($order) {
    $ai = $order[$a] ?? 999;
    $bi = $order[$b] ?? 999;
    if ($ai !== $bi) return $ai <=> $bi;
    return strcmp($a, $b);
});

$totalCount = 0;
foreach ($permissionMap as $perms) {
    $totalCount += count($perms);
}
?>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="d-flex align-items-baseline justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-1">Permissions</h2>
                <p class="muted small mb-0">
                    <?php if ($readOnly): ?>
                        This role is a system role. Its permission set is locked.
                    <?php else: ?>
                        Toggle the capabilities this role should grant.
                        <strong><?= (int) $totalCount ?></strong> total.
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!$readOnly): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="ra-btn ra-btn-outline"
                            data-ra-perm-action="all">
                        Select all
                    </button>
                    <button type="button" class="ra-btn ra-btn-outline"
                            data-ra-perm-action="none">
                        Clear all
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="ra-perm-matrix<?= $readOnly ? ' is-readonly' : '' ?>">
            <?php foreach ($mapKeys as $module): ?>
                <?php
                $perms = $permissionMap[$module];
                $label = $moduleLabels[$module] ?? ucfirst($module);
                $moduleSelected = 0;
                foreach ($perms as $p) {
                    if (isset($assignedSet[(int) $p['id']])) $moduleSelected++;
                }
                ?>
                <details class="ra-perm-group" open>
                    <summary class="ra-perm-group-summary">
                        <span class="ra-perm-group-name"><?= e($label) ?></span>
                        <span class="ra-perm-group-count muted small">
                            <?= (int) $moduleSelected ?> of <?= count($perms) ?> selected
                        </span>
                        <?php if (!$readOnly): ?>
                            <label class="ra-perm-group-toggle">
                                <input type="checkbox"
                                       data-ra-perm-group-toggle="<?= e($module) ?>"
                                       <?= $moduleSelected === count($perms) ? 'checked' : '' ?>>
                                <span>Select all</span>
                            </label>
                        <?php endif; ?>
                    </summary>
                    <div class="ra-perm-group-body">
                        <?php foreach ($perms as $p): ?>
                            <?php
                            $pid     = (int) $p['id'];
                            $slug    = (string) $p['slug'];
                            $desc    = (string) ($p['description'] ?? '');
                            $checked = isset($assignedSet[$pid]);
                            ?>
                            <label class="ra-perm-item" data-ra-perm-module="<?= e($module) ?>">
                                <input type="checkbox"
                                       name="permissions[]"
                                       value="<?= $pid ?>"
                                       <?= $checked ? 'checked' : '' ?>
                                       <?= $readOnly ? 'disabled' : '' ?>>
                                <span class="ra-perm-item-text">
                                    <?= e($desc !== '' ? $desc : $slug) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!$readOnly): ?>
<script>
(function () {
    'use strict';

    document.querySelectorAll('[data-ra-perm-group-toggle]').forEach(function (master) {
        master.addEventListener('change', function () {
            var module = master.getAttribute('data-ra-perm-group-toggle');
            var items = document.querySelectorAll(
                '[data-ra-perm-module="' + module + '"] input[type="checkbox"]'
            );
            items.forEach(function (cb) { cb.checked = master.checked; });
        });
    });

    document.querySelectorAll('[data-ra-perm-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-ra-perm-action');
            var check = action === 'all';
            document.querySelectorAll('.ra-perm-item input[type="checkbox"]').forEach(function (cb) {
                cb.checked = check;
            });
            document.querySelectorAll('[data-ra-perm-group-toggle]').forEach(function (master) {
                master.checked = check;
            });
        });
    });
})();
</script>
<?php endif; ?>
