<?php
/**
 * @var \CorporateAid\Commerce\Models\NavigationMenu $menu
 * @var array $items
 * @var array $resources
 */
?>

<div class="ra-customizer">

    <!-- Topbar -->
    <div class="ra-customizer-topbar d-flex justify-content-between align-items-center px-3 w-100">
        <div class="d-flex align-items-center gap-2">
            <div class="ra-customizer-brand fw-bold d-flex align-items-center">
                <i class="bi bi-list-nested me-2"></i> <?= e($menu->name) ?>
            </div>
        </div>
        <div class="ra-customizer-actions d-flex gap-2 align-items-center">
            <button type="button" class="ra-btn ra-btn-primary ra-btn-sm" onclick="RA_NavModal.open('createItemModal')">
                <i class="bi bi-plus-lg"></i> Add Link
            </button>
            <a href="/commerce/ca-admin/navigation" class="ra-btn ra-btn-outline ra-btn-sm">Close</a>
        </div>
    </div>

    <!-- Body: Sidebar -->
    <div class="ra-customizer-body">
        <div class="ra-customizer-sidebar">
            <div class="p-3 ra-nav-separator d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Items</h6>
            </div>
            <div class="p-2">
                <?php
                $renderItems = function ($items, $depth = 0) use (&$renderItems, $menu, $resources) {
                    foreach ($items as $item) {
                        ?>
                        <div class="d-flex justify-content-between align-items-center py-2 px-2 rounded mb-1 ra-nav-item">
                            <span style="font-size: 0.85rem; padding-left: <?= $depth * 1 ?>rem;">
                                <?= e($item->label) ?>
                            </span>
                            <div class="d-flex gap-1">
                                <button type="button" class="ra-icon-btn btn-sm py-0 px-1" onclick="RA_NavModal.open('editItemModal<?= $item->id ?>')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="/commerce/ca-admin/navigation/items/<?= (int) $item->id ?>/delete" class="d-inline" onsubmit="return confirm('Delete?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
                                    <input type="hidden" name="menu_location" value="custom">
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
                                        <h2 class="h6 mb-0">Edit Item</h2>
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
                        <?php
                        if ($item->hasChildren()) {
                            $renderItems($item->children, $depth + 1);
                        }
                    }
                };

                if (empty($items)): ?>
                    <p class="text-muted small text-center py-3">No items yet. Click + to add one.</p>
                <?php else:
                    $renderItems($items);
                endif;
                ?>
            </div>
        </div>

        <!-- Preview placeholder (custom menus are not rendered on the storefront yet) -->
        <div class="ra-customizer-preview d-flex align-items-center justify-content-center">
            <div class="text-muted small text-center">
                <i class="bi bi-eye-slash" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0">This menu is not rendered on the storefront yet.</p>
                <p class="mb-0">It can be assigned to a location in a future stage.</p>
            </div>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div class="ra-modal" id="createItemModal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_NavModal.close('createItemModal')"></div>
    <div class="ra-modal-panel" role="dialog">
        <form method="POST" action="/commerce/ca-admin/navigation/items">
            <?= csrf_field() ?>
            <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
            <input type="hidden" name="menu_location" value="custom">
            <div class="ra-modal-header">
                <h2 class="h6 mb-0">Add Link</h2>
                <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('createItemModal')"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="ra-modal-body">
                <?php
                $mode = 'create';
                $item = null;
                require __DIR__ . '/_item_form.php';
                ?>
            </div>
            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('createItemModal')">Cancel</button>
                <button type="submit" class="ra-btn ra-btn-primary">Save Item</button>
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
