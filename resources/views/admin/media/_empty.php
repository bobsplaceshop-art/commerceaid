<?php
/**
 * Empty state for the media library grid.
 *
 * @var bool $hasFilters
 */
$hasFilters = (bool) ($hasFilters ?? false);
?>

<div class="ra-card">
    <div class="ra-card-body text-center py-5">
        <i class="bi bi-images" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>

        <?php if ($hasFilters): ?>
            <h2 class="h6 mt-3 mb-1">No files match your filters</h2>
            <p class="muted small mb-3">Try clearing filters or adjusting your search.</p>
            <a href="/commerce/ca-admin/media" class="ra-btn ra-btn-outline">Clear filters</a>
        <?php else: ?>
            <h2 class="h6 mt-3 mb-1">No files yet</h2>
            <p class="muted small mb-3">
                Upload files from the place they are used - Store
                Settings for the store logo and favicon, the product
                editor for product images. Uploaded files appear here
                automatically.
            </p>
        <?php endif; ?>
    </div>
</div>
