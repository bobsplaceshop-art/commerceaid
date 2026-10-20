<?php
/** @var string $slug */
/** @var string $label */
/** @var array<int, \CorporateAid\Commerce\Models\PageBlock> $blocks */
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1"><?= e($label) ?></h1>
        <p class="muted mb-0">Page editor · <code><?= e($slug) ?></code></p>
    </div>
    <a href="/commerce/ca-admin/pages" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to Pages
    </a>
</div>

<div class="ra-card mb-4" style="border-left:3px solid #d4af37;">
    <div class="ra-card-body">
        <h2 class="h6 mb-2">Page builder is coming</h2>
        <p class="mb-0 text-muted">
            The drag-and-drop page builder ships in the next session.
            Right now this page shows the blocks that are already on this page.
            When the builder lands, you'll be able to add new blocks,
            reorder them, and configure each one.
        </p>
    </div>
</div>

<div class="ra-card">
    <div class="ra-card-header">
        <h2 class="h6 mb-0">Blocks (<?= count($blocks) ?>)</h2>
    </div>
    <div class="ra-card-body">
        <?php if (empty($blocks)): ?>
            <p class="text-muted mb-0">This page has no blocks yet.</p>
        <?php else: ?>
            <div class="ra-table-responsive-wrap" data-responsive="scroll">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Order</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($blocks as $block): ?>
                        <tr>
                            <td><?= (int) $block->sortOrder ?></td>
                            <td>
                                <div class="fw-bold"><?= e($block->typeLabel()) ?></div>
                                <div class="text-muted small"><?= e($block->blockType) ?></div>
                            </td>
                            <td>
                                <?php if ($block->isEnabled): ?>
                                    <span class="ra-badge ra-badge-success">Enabled</span>
                                <?php else: ?>
                                    <span class="ra-badge ra-badge-muted">Disabled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
