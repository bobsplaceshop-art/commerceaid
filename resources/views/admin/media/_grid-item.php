<?php
/**
 * One media library grid tile.
 *
 * @var \CorporateAid\Commerce\Models\MediaItem $item
 */
$item = $item ?? null;
if ($item === null) {
    return;
}

$file       = $item->file;
$isImage    = $item->isImage();
$dimensions = $item->dimensionsLabel();
$sizeLabel  = $item->humanSize();

// Thumbnail URL: prefer the 'md' variant in WebP, fall back
// to the source. The variant may not exist for non-raster
// uploads (PDFs) or for files uploaded before the pipeline.
$thumbUrl = null;
if ($isImage) {
    $thumbUrl = $item->variantUrl('md', 'webp');
}

// Category label. The definition isn't loaded here, so we
// use the raw file_category value. It reads as 'store-logo',
// 'product-image', etc.
$category = '';
try {
    $container = \CorporateAid\Core\App::instance()->container();
    $context = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
    if ($context->has()) {
        $repo = $container->get(\CorporateAid\Commerce\Repositories\MediaRepository::class);
        $usage = $repo->findUsageForFile($file->id);
        foreach ($usage as $ref) {
            if ($ref['entity_type'] === 'store' && $ref['entity_id'] === $context->id()) {
                $category = $ref['file_category'];
                break;
            }
        }
    }
} catch (\Throwable) {
    // Silently leave category empty.
}

$categoryLabel = $category !== '' ? ucwords(str_replace(['-', '_'], ' ', $category)) : '';
?>

<li class="ra-media-tile"
    data-file-id="<?= (int) $file->id ?>"
    data-file-name="<?= e($file->originalName) ?>"
    data-file-usage-category="<?= e($category) ?>">

    <div class="ra-media-tile-thumb">
        <?php if ($thumbUrl !== null): ?>
            <img src="<?= e($thumbUrl) ?>"
                 alt="<?= e($item->altText()) ?>"
                 loading="lazy"
                 onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'ra-media-tile-thumb-fallback',textContent:'?'}))">
        <?php else: ?>
            <div class="ra-media-tile-thumb-fallback">
                <i class="bi bi-file-earmark"></i>
            </div>
        <?php endif; ?>
    </div>

    <div class="ra-media-tile-body">
        <div class="ra-media-tile-name" title="<?= e($file->originalName) ?>">
            <?= e($file->originalName) ?>
        </div>
        <div class="ra-media-tile-meta">
            <?= e($sizeLabel) ?>
            <?php if ($dimensions !== ''): ?>
                &middot; <?= e($dimensions) ?>
            <?php endif; ?>
        </div>
        <?php if ($categoryLabel !== ''): ?>
            <div class="ra-media-tile-category"><?= e($categoryLabel) ?></div>
        <?php endif; ?>
    </div>

    <div class="ra-media-tile-actions">
        <button type="button"
                class="ra-icon-btn"
                data-ra-media-detail
                data-file-id="<?= (int) $file->id ?>"
                data-file-name="<?= e($file->originalName) ?>"
                data-file-alt="<?= e($item->altText()) ?>"
                title="Edit metadata">
            <i class="bi bi-pencil"></i>
        </button>
        <button type="button"
                class="ra-icon-btn"
                data-ra-media-delete
                data-file-id="<?= (int) $file->id ?>"
                data-file-name="<?= e($file->originalName) ?>"
                title="Delete">
            <i class="bi bi-trash"></i>
        </button>
    </div>
</li>
