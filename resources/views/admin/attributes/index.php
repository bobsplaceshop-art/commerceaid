<?php
/**
 * Attributes list.
 *
 * Shows each attribute definition with its values as chips.
 *
 * @var array $attributes  [{id, slug, name, input_type, is_variant_axis, sort_order, values: [...]}, ...]
 */
$attributes = $attributes ?? [];
$total = count($attributes);
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Attributes</h1>
        <p class="muted mb-0">
            <?= $total ?> attribute<?= $total === 1 ? '' : 's' ?>
        </p>
    </div>
    <div>
        <a href="/commerce/ca-admin/attributes/create" class="ra-btn ra-btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New attribute
        </a>
    </div>
</div>

<?php if (empty($attributes)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-input-cursor-text" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No attributes yet</h2>
            <p class="muted small mb-3">
                Attributes define the options that become your variants.
                Common examples: <strong>Color</strong>, <strong>Size</strong>, <strong>Material</strong>.
            </p>
            <a href="/commerce/ca-admin/attributes/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Create your first attribute
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="scroll">
                <table class="ra-table">
            <thead>
                <tr>
                    <th style="width:220px;">Name</th>
                    <th style="width:160px;">Slug</th>
                    <th style="width:120px;">Input</th>
                    <th>Values</th>
                    <th style="width:180px;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attributes as $attr): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-input-cursor-text" style="color:var(--ra-muted,#94a3b8);"></i>
                                <span><?= e($attr['name']) ?></span>
                                <?php if (!empty($attr['is_variant_axis'])): ?>
                                    <span class="ra-badge ra-badge-success" style="font-size:.65rem;">Variant</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><code class="muted small"><?= e($attr['slug']) ?></code></td>
                        <td>
                            <span class="muted small">
                                <?= e(ucfirst((string) $attr['input_type'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (empty($attr['values'])): ?>
                                <span class="muted small">-</span>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($attr['values'] as $v): ?>
                                        <?php if ($attr['input_type'] === 'color' && !empty($v['color_hex'])): ?>
                                            <span class="ra-value-chip" title="<?= e($v['value']) ?>">
                                                <span class="ra-color-swatch" style="background:<?= e($v['color_hex']) ?>;"></span>
                                                <?= e($v['value']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="ra-value-chip"><?= e($v['value']) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="/commerce/ca-admin/attributes/<?= (int) $attr['id'] ?>/edit"
                               class="ra-icon-btn" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="ra-icon-btn"
                                    onclick='RA_DeleteAttribute.open(<?= (int) $attr['id'] ?>, <?= json_encode($attr['name']) ?>)'
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
                </div>
    </div>
<?php endif; ?>

<?= view_partial('admin/attributes/_delete-modal') ?>

<style>
.ra-value-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .15rem .55rem;
    background: var(--ra-hover, rgba(148, 163, 184, 0.15));
    color: var(--ra-fg, #0f172a);
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 500;
    white-space: nowrap;
}
.ra-color-swatch {
    display: inline-block;
    width: .85rem;
    height: .85rem;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
}
</style>
