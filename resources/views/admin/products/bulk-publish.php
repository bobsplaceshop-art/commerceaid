<?php
/**
 * Bulk Publish screen. 
 * Lists all drafts. Only drafts with images are publishable.
 */
$result = $result ?? ['items' => [], 'total' => 0];
$items  = $result['items'];

$hasDrafts = !empty($items);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Publish Drafts</h1>
        <p class="muted mb-0">Select products to make them active on the storefront. Only products with images can be published.</p>
    </div>
    <a href="/commerce/ca-admin/products" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to Products
    </a>
</div>

<?php if (!$hasDrafts): ?>
    <div class="ra-card text-center py-5">
        <i class="bi bi-check-circle" style="font-size:2.5rem;color:var(--ra-muted);"></i>
        <h2 class="h5 mt-3">All caught up!</h2>
        <p class="muted">There are no draft products waiting to be published.</p>
    </div>
<?php else: ?>
    <form method="POST" action="/commerce/ca-admin/products/bulk-publish">
        <?= csrf_field() ?>
        <div class="ra-card">
            <div class="ra-table-responsive-wrap" data-responsive="cards">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">
                                <input type="checkbox" class="form-check-input" id="select-all">
                            </th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Readiness</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $p): ?>
                            <?php 
                            // Check for images to show readiness
                            $hasImage = !empty($p->images); 
                            $readiness = $hasImage ? '<span class="ra-badge ra-badge-success">Ready</span>' : '<span class="ra-badge ra-badge-danger">No Image</span>';
                            ?>
                            <tr>
                                <td data-col="half">
                                    <input type="checkbox" name="product_ids[]" value="<?= (int)$p->id ?>" class="form-check-input product-checkbox" <?= $hasImage ? '' : 'disabled' ?>>
                                </td>
                                <td data-col="full">
                                    <?= e($p->name) ?>
                                    <?php if (!$hasImage): ?>
                                        <div class="small text-danger mt-1">Upload an image to enable publishing.</div>
                                    <?php endif; ?>
                                </td>
                                <td data-col="half"><?= e($p->sku ?? '-') ?></td>
                                <td data-col="half"><?= number_format($p->price, 2) ?></td>
                                <td data-col="half"><?= $readiness ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4 d-flex justify-content-end">
            <button type="submit" class="ra-btn ra-btn-primary">
                <i class="bi bi-cloud-arrow-up me-1"></i> Publish Selected Products
            </button>
        </div>
    </form>

    <script>
        document.getElementById('select-all').addEventListener('change', function(e) {
            var checkboxes = document.querySelectorAll('.product-checkbox');
            for (var i = 0; i < checkboxes.length; i++) {
                if (!checkboxes[i].disabled) {
                    checkboxes[i].checked = e.target.checked;
                }
            }
        });
    </script>
<?php endif; ?>
