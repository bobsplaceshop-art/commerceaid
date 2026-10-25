<?php
/**
 * @var \CorporateAid\Commerce\Models\NavigationMenu $menu
 * @var array $items
 * @var array $trustItems
 * @var array $resources
 */
?>

<div class="row g-4">
    <!-- Left: Editor -->
    <div class="col-lg-6">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 mb-0">Header Builder</h1>
            <button class="ra-btn ra-btn-primary btn-sm" onclick="RA_NavModal.open('createItemModal')">
                <i class="bi bi-plus-lg"></i> Add Link
            </button>
        </div>

        <div class="ra-card">
            <div class="ra-table-responsive-wrap">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Order</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $renderItems = function ($items, $depth = 0) use (&$renderItems, $menu, $resources) {
                            foreach ($items as $item) {
                                ?>
                                <tr>
                                    <td>
                                        <div style="padding-left:<?= $depth * 1.5 ?>rem;">
                                            <?= e($item->label) ?>
                                            <?php if ($item->isHighlight): ?>
                                                <span class="ra-badge ra-badge-warning ms-1" style="font-size:.6rem;">Highlight</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= (int) $item->sortOrder ?></td>
                                    <td class="text-end">
                                        <button type="button" class="ra-icon-btn" onclick="RA_NavModal.open('editItemModal<?= $item->id ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="/commerce/ca-admin/navigation/items/<?= (int) $item->id ?>/delete" class="d-inline" onsubmit="return confirm('Delete?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
                                            <input type="hidden" name="menu_location" value="header">
                                            <button type="submit" class="ra-icon-btn text-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
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
                            <tr><td colspan="3" class="text-center text-muted py-4">No header items yet.</td></tr>
                        <?php else:
                            $renderItems($items);
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right: Live Preview -->
    <div class="col-lg-6">
        <div class="ra-card h-100 d-flex flex-column">
            <div class="ra-card-header d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0">Live Preview</h3>
                <button class="ra-btn ra-btn-outline btn-sm" onclick="document.getElementById('preview-iframe').contentWindow.location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
            <div class="ra-card-body p-0 flex-grow-1" style="height: 80vh; min-height: 600px; background: #f8f9fa; border-bottom-left-radius: inherit; border-bottom-right-radius: inherit; overflow: hidden;">
                <iframe src="<?= e($previewUrl) ?>" id="preview-iframe" title="Storefront preview" style="width: 100%; height: 100%; border: 0; display: block;"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="ra-card mt-4">
    <div class="ra-card-header d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">Trust Bar Items</h3>
    </div>
    <div class="ra-table-responsive-wrap">
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Icon &amp; Label</th>
                    <th>Subtitle</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trustItems as $tItem): ?>
                    <tr>
                        <td>
                            <i class="bi <?= e($tItem->icon ?? 'bi-star') ?> me-2"></i>
                            <?= e($tItem->label) ?>
                        </td>
                        <td><?= e($tItem->subtitle ?? '') ?></td>
                        <td class="text-end">
                            <button type="button" class="ra-icon-btn" onclick="RA_NavModal.open('editItemModal<?= $tItem->id ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="/commerce/ca-admin/navigation/items/<?= (int) $tItem->id ?>/delete" class="d-inline" onsubmit="return confirm('Delete?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
                                <input type="hidden" name="menu_location" value="header">
                                <button type="submit" class="ra-icon-btn text-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <div class="ra-modal" id="editItemModal<?= $tItem->id ?>" style="display:none;">
                        <div class="ra-modal-backdrop" onclick="RA_NavModal.close('editItemModal<?= $tItem->id ?>')"></div>
                        <div class="ra-modal-panel" role="dialog">
                            <form method="POST" action="/commerce/ca-admin/navigation/items/<?= (int) $tItem->id ?>">
                                <?= csrf_field() ?>
                                <div class="ra-modal-header">
                                    <h2 class="h6 mb-0">Edit Trust Item</h2>
                                    <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('editItemModal<?= $tItem->id ?>')"><i class="bi bi-x-lg"></i></button>
                                </div>
                                <div class="ra-modal-body">
                                    <?php $mode = 'edit'; $item = $tItem; require __DIR__ . '/_item_form.php'; ?>
                                </div>
                                <div class="ra-modal-footer">
                                    <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('editItemModal<?= $tItem->id ?>')">Cancel</button>
                                    <button type="submit" class="ra-btn ra-btn-primary">Save Item</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($trustItems)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">No trust bar items.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="ra-modal" id="createItemModal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_NavModal.close('createItemModal')"></div>
    <div class="ra-modal-panel" role="dialog">
        <form method="POST" action="/commerce/ca-admin/navigation/items">
            <?= csrf_field() ?>
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
window.RA_NavModal = {
    open: function(id) {
        document.getElementById(id).style.display = 'flex';
        document.body.style.overflow = 'hidden';
    },
    close: function(id) {
        document.getElementById(id).style.display = 'none';
        document.body.style.overflow = '';
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
