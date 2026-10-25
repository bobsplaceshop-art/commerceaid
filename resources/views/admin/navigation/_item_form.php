<?php
/**
 * @var string $mode 'create' or 'edit'
 * @var \CorporateAid\Commerce\Models\NavigationMenu $menu
 * @var \CorporateAid\Commerce\Models\NavigationItem|null $item
 * @var array $resources
 */
$isEdit = $mode === 'edit' && $item !== null;
$actionUrl = $isEdit
    ? '/commerce/ca-admin/navigation/items/' . (int) $item->id
    : '/commerce/ca-admin/navigation/items';
?>
<form method="POST" action="<?= $actionUrl ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="menu_id" value="<?= (int) $menu->id ?>">
    <input type="hidden" name="menu_location" value="<?= e($menu->location) ?>">
    
    <?php if ($isEdit): ?>
        <input type="hidden" name="column_id" value="<?= $item->columnId !== null ? (int) $item->columnId : '' ?>">
        <input type="hidden" name="parent_id" value="<?= $item->parentId !== null ? (int) $item->parentId : '' ?>">
    <?php else: ?>
        <input type="hidden" name="column_id" value="<?= isset($item) && $item->columnId !== null ? (int) $item->columnId : '' ?>">
        <input type="hidden" name="parent_id" value="">
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Label</label>
        <input type="text" name="label" class="form-control" 
               value="<?= $isEdit ? e($item->label) : '' ?>" required>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Icon Class (optional)</label>
            <input type="text" name="icon" class="form-control" placeholder="e.g. bi-star"
                   value="<?= $isEdit ? e($item->icon ?? '') : '' ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Subtitle (optional)</label>
            <input type="text" name="subtitle" class="form-control" 
                   value="<?= $isEdit ? e($item->subtitle ?? '') : '' ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Destination</label>
        <select name="resource_type" class="form-select mb-2" id="dest-type-<?= $isEdit ? $item->id : 'new' ?>">
            <option value="">Custom URL</option>
            <optgroup label="Categories">
                <?php foreach ($resources['category'] as $r): ?>
                    <option value="category_<?= (int) $r['id'] ?>"
                        <?= $isEdit && $item->resourceType === 'category' && $item->resourceId === (int) $r['id'] ? 'selected' : '' ?>>
                        <?= e($r['label']) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="Products">
                <?php foreach ($resources['product'] as $r): ?>
                    <option value="product_<?= (int) $r['id'] ?>"
                        <?= $isEdit && $item->resourceType === 'product' && $item->resourceId === (int) $r['id'] ? 'selected' : '' ?>>
                        <?= e($r['label']) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="Pages">
                <?php foreach ($resources['page'] as $r): ?>
                    <option value="page_<?= (int) $r['id'] ?>"
                        <?= $isEdit && $item->resourceType === 'page' && $item->resourceId === (int) $r['id'] ? 'selected' : '' ?>>
                        <?= e($r['label']) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="Legal Pages">
                <?php foreach ($resources['legal_page'] as $r): ?>
                    <option value="legal_page_<?= (int) $r['id'] ?>"
                        <?= $isEdit && $item->resourceType === 'legal_page' && $item->resourceId === (int) $r['id'] ? 'selected' : '' ?>>
                        <?= e($r['label']) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        </select>
        
        <input type="text" name="url" class="form-control" placeholder="/custom-url or https://..."
               value="<?= $isEdit && !$item->resourceType ? e($item->url) : '' ?>" 
               id="dest-url-<?= $isEdit ? $item->id : 'new' ?>">
    </div>

    <div class="row mb-3">
        <div class="col-6">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" 
                   value="<?= $isEdit ? (int) $item->sortOrder : 0 ?>">
        </div>
        <div class="col-6 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_highlight" value="1" id="is_highlight_<?= $isEdit ? $item->id : 'new' ?>"
                    <?= $isEdit && $item->isHighlight ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_highlight_<?= $isEdit ? $item->id : 'new' ?>">Highlight (e.g., Sale)</label>
            </div>
        </div>
    </div>

    <div class="text-end">
        <button type="button" class="ra-btn ra-btn-outline" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="ra-btn ra-btn-primary">Save Item</button>
    </div>
</form>

<script>
(function() {
    var select = document.getElementById('dest-type-<?= $isEdit ? $item->id : 'new' ?>');
    var urlInput = document.getElementById('dest-url-<?= $isEdit ? $item->id : 'new' ?>');
    if (select && urlInput) {
        select.addEventListener('change', function() {
            urlInput.style.display = this.value === '' ? 'block' : 'none';
        });
        urlInput.style.display = select.value === '' ? 'block' : 'none';
    }
})();
</script>
