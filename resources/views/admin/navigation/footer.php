<?php
/**
 * @var \CorporateAid\Commerce\Models\NavigationMenu $menu
 * @var array $columns
 * @var array $contacts
 * @var array $resources
 * @var string $previewUrl
 */

// Split columns into the "footer_top" column and the grid columns.
$footerTopColumn = null;
$gridColumns = [];
foreach ($columns as $col) {
    if ($col->columnKey === 'footer_top') {
        $footerTopColumn = $col;
    } else {
        $gridColumns[] = $col;
    }
}
?>

<div class="ra-customizer">

    <!-- Topbar -->
    <div class="ra-customizer-topbar d-flex justify-content-between align-items-center px-3 w-100">
        <div class="d-flex align-items-center gap-2">
            <div class="ra-customizer-brand fw-bold d-flex align-items-center">
                <i class="bi bi-layout-text-window me-2"></i> Footer Builder
            </div>
        </div>
        <div class="ra-customizer-actions d-flex gap-2 align-items-center">
            <button type="button" class="ra-btn ra-btn-primary ra-btn-sm" onclick="RA_NavModal.open('createColumnModal')">
                <i class="bi bi-plus-lg"></i> Add Column
            </button>
            <a href="/commerce/ca-admin/navigation" class="ra-btn ra-btn-outline ra-btn-sm">Close</a>
        </div>
    </div>

    <!-- Body: Sidebar + Preview -->
    <div class="ra-customizer-body">

        <!-- Sidebar -->
        <div class="ra-customizer-sidebar">

            <!-- Footer Top (special) -->
            <?php if ($footerTopColumn !== null): ?>
            <div class="ra-nav-separator">
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><?= e($footerTopColumn->heading) ?></h6>
                    <button type="button" class="ra-icon-btn" onclick="RA_NavModal.open('editColumnModal<?= $footerTopColumn->id ?>')">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
                <div class="ra-modal" id="editColumnModal<?= $footerTopColumn->id ?>" style="display:none;">
                    <div class="ra-modal-backdrop" onclick="RA_NavModal.close('editColumnModal<?= $footerTopColumn->id ?>')"></div>
                    <div class="ra-modal-panel" role="dialog">
                        <form method="POST" action="/commerce/ca-admin/navigation/columns/<?= (int) $footerTopColumn->id ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
                            <input type="hidden" name="menu_location" value="footer">
                            <div class="ra-modal-header">
                                <h2 class="h6 mb-0">Edit Column</h2>
                                <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('editColumnModal<?= $footerTopColumn->id ?>')"><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div class="ra-modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Heading</label>
                                    <input type="text" name="heading" class="form-control" value="<?= e($footerTopColumn->heading) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Sort Order</label>
                                    <input type="number" name="sort_order" class="form-control" value="<?= (int) $footerTopColumn->sortOrder ?>">
                                </div>
                            </div>
                            <div class="ra-modal-footer d-flex justify-content-between">
                                <button type="submit" form="delete-column-<?= $footerTopColumn->id ?>" class="ra-btn ra-btn-outline text-danger border-danger" onclick="return confirm('Delete this column and all its items?');">
                                    Delete Column
                                </button>
                                <div>
                                    <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('editColumnModal<?= $footerTopColumn->id ?>')">Cancel</button>
                                    <button type="submit" class="ra-btn ra-btn-primary">Save</button>
                                </div>
                            </div>
                        </form>
                        <form id="delete-column-<?= $footerTopColumn->id ?>" method="POST" action="/commerce/ca-admin/navigation/columns/<?= (int) $footerTopColumn->id ?>/delete" style="display:none;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
                            <input type="hidden" name="menu_location" value="footer">
                        </form>
                    </div>
                </div>
                <div class="px-2 pb-2">
                    <?php foreach ($footerTopColumn->items as $item): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 px-2 rounded mb-1 ra-nav-item">
                        <span style="font-size: 0.85rem;">
                            <?php if (!empty($item->icon)): ?>
                                <i class="bi <?= e($item->icon) ?> me-1"></i>
                            <?php endif; ?>
                            <?= e($item->label) ?>
                        </span>
                        <div class="d-flex gap-1">
                            <button type="button" class="ra-icon-btn btn-sm py-0 px-1" onclick="RA_NavModal.open('editItemModal<?= $item->id ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="/commerce/ca-admin/navigation/items/<?= (int) $item->id ?>/delete" class="d-inline" onsubmit="return confirm('Delete?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
                                <input type="hidden" name="menu_location" value="footer">
                                <button type="submit" class="ra-icon-btn text-danger btn-sm py-0 px-1">
                                    <i class="bi bi-x"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="ra-modal" id="editItemModal<?= $item->id ?>" style="display:none;">
                        <div class="ra-modal-backdrop" onclick="RA_NavModal.close('editItemModal<?= $item->id ?>')"></div>
                        <div class="ra-modal-panel" role="dialog">
                            <form method="POST" action="/commerce/ca-admin/navigation/items/<?= (int) $item->id ?>">
                                <?= csrf_field() ?>
                                <div class="ra-modal-header">
                                    <h2 class="h6 mb-0">Edit Footer Item</h2>
                                    <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('editItemModal<?= $item->id ?>')"><i class="bi bi-x-lg"></i></button>
                                </div>
                                <div class="ra-modal-body">
                                    <?php $mode = 'edit'; require __DIR__ . '/_item_form.php'; ?>
                                </div>
                                <div class="ra-modal-footer">
                                    <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('editItemModal<?= $item->id ?>')">Cancel</button>
                                    <button type="submit" class="ra-btn ra-btn-primary">Save Item</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Columns -->
            <?php foreach ($gridColumns as $col): ?>
            <div class="ra-nav-separator">
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><?= e($col->heading) ?></h6>
                    <button type="button" class="ra-icon-btn" onclick="RA_NavModal.open('editColumnModal<?= $col->id ?>')">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
                <div class="ra-modal" id="editColumnModal<?= $col->id ?>" style="display:none;">
                    <div class="ra-modal-backdrop" onclick="RA_NavModal.close('editColumnModal<?= $col->id ?>')"></div>
                    <div class="ra-modal-panel" role="dialog">
                        <form method="POST" action="/commerce/ca-admin/navigation/columns/<?= (int) $col->id ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
                            <input type="hidden" name="menu_location" value="footer">
                            <div class="ra-modal-header">
                                <h2 class="h6 mb-0">Edit Column</h2>
                                <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('editColumnModal<?= $col->id ?>')"><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div class="ra-modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Heading</label>
                                    <input type="text" name="heading" class="form-control" value="<?= e($col->heading) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Sort Order</label>
                                    <input type="number" name="sort_order" class="form-control" value="<?= (int) $col->sortOrder ?>">
                                </div>
                            </div>
                            <div class="ra-modal-footer d-flex justify-content-between">
                                <button type="submit" form="delete-column-<?= $col->id ?>" class="ra-btn ra-btn-outline text-danger border-danger" onclick="return confirm('Delete this column and all its items?');">
                                    Delete Column
                                </button>
                                <div>
                                    <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('editColumnModal<?= $col->id ?>')">Cancel</button>
                                    <button type="submit" class="ra-btn ra-btn-primary">Save</button>
                                </div>
                            </div>
                        </form>
                        <form id="delete-column-<?= $col->id ?>" method="POST" action="/commerce/ca-admin/navigation/columns/<?= (int) $col->id ?>/delete" style="display:none;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
                            <input type="hidden" name="menu_location" value="footer">
                        </form>
                    </div>
                </div>
                <div class="px-2 pb-2">
                    <?php foreach ($col->items as $item): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 px-2 rounded mb-1 ra-nav-item">
                        <span style="font-size: 0.85rem;"><?= e($item->label) ?></span>
                        <div class="d-flex gap-1">
                            <button type="button" class="ra-icon-btn btn-sm py-0 px-1" onclick="RA_NavModal.open('editItemModal<?= $item->id ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="/commerce/ca-admin/navigation/items/<?= (int) $item->id ?>/delete" class="d-inline" onsubmit="return confirm('Delete?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
                                <input type="hidden" name="menu_location" value="footer">
                                <button type="submit" class="ra-icon-btn text-danger btn-sm py-0 px-1">
                                    <i class="bi bi-x"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="ra-modal" id="editItemModal<?= $item->id ?>" style="display:none;">
                        <div class="ra-modal-backdrop" onclick="RA_NavModal.close('editItemModal<?= $item->id ?>')"></div>
                        <div class="ra-modal-panel" role="dialog">
                            <form method="POST" action="/commerce/ca-admin/navigation/items/<?= (int) $item->id ?>">
                                <?= csrf_field() ?>
                                <div class="ra-modal-header">
                                    <h2 class="h6 mb-0">Edit Footer Item</h2>
                                    <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('editItemModal<?= $item->id ?>')"><i class="bi bi-x-lg"></i></button>
                                </div>
                                <div class="ra-modal-body">
                                    <?php $mode = 'edit'; require __DIR__ . '/_item_form.php'; ?>
                                </div>
                                <div class="ra-modal-footer">
                                    <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('editItemModal<?= $item->id ?>')">Cancel</button>
                                    <button type="submit" class="ra-btn ra-btn-primary">Save Item</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center py-2">
                        <button class="ra-btn ra-btn-outline btn-sm w-100" onclick="RA_NavModal.open('addFooterItem<?= $col->id ?>')">
                            + Add Link
                        </button>
                    </div>
                </div>
            </div>

            <div class="ra-modal" id="addFooterItem<?= $col->id ?>" style="display:none;">
                <div class="ra-modal-backdrop" onclick="RA_NavModal.close('addFooterItem<?= $col->id ?>')"></div>
                <div class="ra-modal-panel" role="dialog">
                    <form method="POST" action="/commerce/ca-admin/navigation/items">
                        <?= csrf_field() ?>
                        <div class="ra-modal-header">
                            <h2 class="h6 mb-0">Add Footer Link</h2>
                            <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('addFooterItem<?= $col->id ?>')"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="ra-modal-body">
                            <?php
                            $mode = 'create';
                            $item = new \CorporateAid\Commerce\Models\NavigationItem();
                            $item->columnId = $col->id;
                            require __DIR__ . '/_item_form.php';
                            ?>
                        </div>
                        <div class="ra-modal-footer">
                            <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('addFooterItem<?= $col->id ?>')">Cancel</button>
                            <button type="submit" class="ra-btn ra-btn-primary">Save Item</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Contact Details -->
            <div class="ra-nav-separator">
                <div class="p-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Contact Details</h6>
                    <button class="ra-btn ra-btn-outline ra-btn-sm" onclick="RA_NavModal.open('addContactModal')">
                        +
                    </button>
                </div>
                <div class="px-2 pb-2">
                    <?php foreach ($contacts as $contact): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 px-2 rounded mb-1 ra-nav-item">
                        <span style="font-size: 0.85rem;">
                            <span class="ra-badge ra-badge-muted" style="font-size:.65rem;"><?= e($contact->type) ?></span>
                            <?= e($contact->label) ?>
                        </span>
                        <form method="POST" action="/commerce/ca-admin/navigation/contact/<?= (int) $contact->id ?>/delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <?= csrf_field() ?>
                            <button type="submit" class="ra-icon-btn text-danger btn-sm py-0 px-1">
                                <i class="bi bi-x"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($contacts)): ?>
                        <p class="text-muted small text-center py-3">No contact rows.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="ra-customizer-preview">
            <div class="ra-preview-frame-wrapper">
                <iframe src="<?= e($previewUrl) ?>" id="preview-iframe" title="Storefront preview"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Add Contact Modal -->
<div class="ra-modal" id="addContactModal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_NavModal.close('addContactModal')"></div>
    <div class="ra-modal-panel" role="dialog">
        <form method="POST" action="/commerce/ca-admin/navigation/contact">
            <?= csrf_field() ?>
            <div class="ra-modal-header">
                <h2 class="h6 mb-0">Add Contact Row</h2>
                <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('addContactModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="ra-modal-body">
                <div class="mb-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="email">Email</option>
                        <option value="phone">Phone</option>
                        <option value="address">Address</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Label</label>
                    <input type="text" name="label" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Value</label>
                    <input type="text" name="value" class="form-control" required>
                </div>
            </div>
            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('addContactModal')">Cancel</button>
                <button type="submit" class="ra-btn ra-btn-primary">Save Contact</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Column Modal -->
<div class="ra-modal" id="createColumnModal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_NavModal.close('createColumnModal')"></div>
    <div class="ra-modal-panel" role="dialog">
        <form method="POST" action="/commerce/ca-admin/navigation/columns">
            <?= csrf_field() ?>
            <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
            <input type="hidden" name="menu_location" value="footer">
            <div class="ra-modal-header">
                <h2 class="h6 mb-0">Add Column</h2>
                <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('createColumnModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="ra-modal-body">
                <div class="mb-3">
                    <label class="form-label">Key (lowercase, underscores)</label>
                    <input type="text" name="column_key" class="form-control" placeholder="e.g. shipping" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Heading</label>
                    <input type="text" name="heading" class="form-control" placeholder="e.g. Shipping" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
            </div>
            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('createColumnModal')">Cancel</button>
                <button type="submit" class="ra-btn ra-btn-primary">Save</button>
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
