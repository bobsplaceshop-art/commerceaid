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
    <div class="col-lg-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 mb-0">Header Builder</h1>
            <button class="ra-btn ra-btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createItemModal">
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
                                        <button type="button" class="ra-icon-btn" data-bs-toggle="modal" data-bs-target="#editItemModal<?= $item->id ?>">
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
                                <?php
                                // Modal for edit
                                ?>
                                <div class="modal fade" id="editItemModal<?= $item->id ?>" tabindex="-1">
                                    <div class="modal-dialog"><div class="modal-content"><div class="modal-body">
                                        <?php $mode = 'edit'; require __DIR__ . '/_item_form.php'; ?>
                                    </div></div></div>
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
    <div class="col-lg-7">
        <div class="ra-card h-100 d-flex flex-column">
            <div class="ra-card-header d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0">Live Preview</h3>
                <button class="ra-btn ra-btn-outline btn-sm" onclick="document.getElementById('preview-iframe').contentWindow.location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
            <div class="ra-card-body p-0 flex-grow-1" style="min-height: 600px; background: #f8f9fa; border-bottom-left-radius: inherit; border-bottom-right-radius: inherit; overflow: hidden;">
                <iframe src="<?= e($previewUrl) ?>" id="preview-iframe" title="Storefront preview" style="width: 100%; height: 100%; border: 0; min-height: 600px; display: block;"></iframe>
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
                                    <button type="button" class="ra-icon-btn" data-bs-toggle="modal" data-bs-target="#editItemModal<?= $tItem->id ?>">
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
                            <div class="modal fade" id="editItemModal<?= $tItem->id ?>" tabindex="-1">
                                <div class="modal-dialog"><div class="modal-content"><div class="modal-body">
                                    <?php $mode = 'edit'; $item = $tItem; require __DIR__ . '/_item_form.php'; ?>
                                </div></div></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($trustItems)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">No trust bar items.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<!-- Create Modal -->
<div class="modal fade" id="createItemModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-body">
        <?php
        $mode = 'create';
        $item = null;
        require __DIR__ . '/_item_form.php';
        ?>
    </div></div></div>
</div>

<script>
// Reload the iframe whenever a form inside it is submitted (via standard POST redirect)
// or if the user makes a change. For now, we just rely on the page reload.
// In the future, we could intercept the form submission and trigger postMessage.
</script>
