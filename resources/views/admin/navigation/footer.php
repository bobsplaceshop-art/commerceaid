<?php
/**
 * @var \CorporateAid\Commerce\Models\NavigationMenu $menu
 * @var array $columns
 * @var array $contacts
 * @var array $resources
 */
?>
<div class="row g-4">
    <!-- Left: Editor -->
    <div class="col-lg-6">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 mb-0">Footer Builder</h1>
        </div>

        <div class="row g-3">
            <?php foreach ($columns as $col): ?>
            <div class="col-md-6">
                <div class="ra-card h-100">
                    <div class="ra-card-header d-flex justify-content-between align-items-center py-2">
                        <h3 class="h6 mb-0"><?= e($col->heading) ?></h3>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($col->items as $item): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                            <span style="font-size: 0.9rem;"><?= e($item->label) ?></span>
                            <form method="POST" action="/commerce/ca-admin/navigation/items/<?= (int) $item->id ?>/delete" class="d-inline" onsubmit="return confirm('Delete?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
                                <input type="hidden" name="menu_location" value="footer">
                                <button type="submit" class="ra-icon-btn text-danger btn-sm py-0 px-1">
                                    <i class="bi bi-x"></i>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if ($col->columnKey === 'contact'): ?>
                            <?php foreach ($contacts as $contact): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center text-muted py-2 px-3" style="font-size: 0.85rem;">
                                <?= e($contact->label) ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="ra-card-footer text-center py-2">
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
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
            <h2 class="h5 mb-0">Contact Details</h2>
            <button class="ra-btn ra-btn-outline btn-sm" onclick="RA_NavModal.open('addContactModal')">
                + Add Row
            </button>
        </div>

        <div class="ra-card">
            <div class="ra-table-responsive-wrap">
                <table class="ra-table">
                    <thead><tr><th>Type</th><th>Label</th><th>Value</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td><span class="ra-badge" style="font-size:.7rem;"><?= e($contact->type) ?></span></td>
                            <td><?= e($contact->label) ?></td>
                            <td><?= e($contact->value) ?></td>
                            <td class="text-end">
                                <form method="POST" action="/commerce/ca-admin/navigation/contact/<?= (int) $contact->id ?>/delete" class="d-inline" onsubmit="return confirm('Delete?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-icon-btn text-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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

<script>
window.RA_NavModal = window.RA_NavModal || {
    open: function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    },
    close: function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.style.display = 'none';
            document.body.style.overflow = '';
        }
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
