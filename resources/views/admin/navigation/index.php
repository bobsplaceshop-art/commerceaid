<?php
/**
 * @var array $menus
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Menus</h1>
    <button type="button" class="ra-btn ra-btn-primary" onclick="RA_NavModal.open('createMenuModal')">
        <i class="bi bi-plus-lg"></i> New menu
    </button>
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
                            <a href="/commerce/ca-admin/navigation/menu/<?= e($menu->slug) ?>" class="ra-btn ra-btn-outline btn-sm">Edit</a>
                            <form method="POST" action="/commerce/ca-admin/navigation/menus/<?= (int) $menu->id ?>/delete" class="d-inline" onsubmit="return confirm('Delete this menu and all its items?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="ra-icon-btn text-danger" title="Delete menu">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($menus)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No menus yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Menu Modal -->
<div class="ra-modal" id="createMenuModal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_NavModal.close('createMenuModal')"></div>
    <div class="ra-modal-panel" role="dialog">
        <form method="POST" action="/commerce/ca-admin/navigation/menus">
            <?= csrf_field() ?>
            <div class="ra-modal-header">
                <h2 class="h6 mb-0">New menu</h2>
                <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('createMenuModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="ra-modal-body">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-control" placeholder="e.g. mega-menu" required>
                    <div class="form-text">Lowercase, hyphens only. Used in the URL and in the theme.</div>
                </div>
            </div>
            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('createMenuModal')">Cancel</button>
                <button type="submit" class="ra-btn ra-btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<script>
window.RA_NavModal = window.RA_NavModal || {
    open: function(id) {
        var el = document.getElementById(id);
        if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    },
    close: function(id) {
        var el = document.getElementById(id);
        if (el) { el.style.display = 'none'; document.body.style.overflow = ''; }
    }
};
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.ra-modal').forEach(function(m) {
            if (m.style.display === 'flex') {
                m.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    }
});
</script>
