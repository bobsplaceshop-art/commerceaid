<?php
/**
 * @var array $menus
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Menus</h1>
</div>

<div class="ra-card">
    <div class="ra-table-responsive-wrap">
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($menus as $menu): ?>
                <tr>
                    <td><?= e($menu->name) ?></td>
                    <td><span class="ra-badge ra-badge-muted"><?= e($menu->location) ?></span></td>
                    <td><?= $menu->isActive ? '<span class="ra-badge ra-badge-success">Active</span>' : '<span class="ra-badge ra-badge-muted">Inactive</span>' ?></td>
                    <td class="text-end">
                        <?php if ($menu->location === 'header'): ?>
                            <a href="/commerce/ca-admin/navigation/header" class="ra-btn ra-btn-outline btn-sm">Edit Header</a>
                        <?php elseif ($menu->location === 'footer'): ?>
                            <a href="/commerce/ca-admin/navigation/footer" class="ra-btn ra-btn-outline btn-sm">Edit Footer</a>
                        <?php else: ?>
                            <span class="text-muted small">Custom Menu</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
