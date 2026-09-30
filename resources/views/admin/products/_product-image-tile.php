<?php
/**
 * One tile in the product image gallery.
 *
 * Scope (passed from _tab_images.php):
 *
 *   $entry       array{image: ProductImage, media: MediaItem}
 *   $index       int   iteration index (unused now, kept for
 *                      future ordering hints)
 *
 * Every input is scoped with images[<imageId>][...]. The
 * ProductController::syncImages() write path reads them via
 * $_POST['images'][<id>].
 *
 * No inline onclick. No JSON encoding into attributes. Every
 * string passes through e(). Pattern 13.
 *
 * The tile is pure render. Behavior (drag reorder, remove,
 * primary radio handling) lives in product-images-admin.js.
 */

$entry = $entry ?? null;

if ($entry === null) {
    return;
}

$image     = $entry['image'];
$media     = $entry['media'];
$imageId   = (int) $image->id;
$isPrimary = (bool) $image->isPrimary;
$altText   = (string) ($image->altText ?? '');

// Thumbnail: the md variant in WebP. Matches the media
// library grid's choice (admin/media/_grid-item.php). The
// <picture> falls back to the source via the img tag.
$thumbUrl  = $media->variantUrl('md', 'webp');
$sourceUrl = $media->url();
$mediaAlt  = $media->altText();
?>
<div class="ra-image-tile<?= $isPrimary ? ' is-primary' : '' ?>"
     data-ra-image-tile
     data-image-id="<?= $imageId ?>"
     draggable="true">

    <div class="ra-image-tile-handle" title="Drag to reorder">
        <i class="bi bi-grip-vertical" aria-hidden="true"></i>
    </div>

    <div class="ra-image-tile-thumb">
        <picture>
            <source type="image/webp" srcset="<?= e($thumbUrl) ?>">
            <img src="<?= e($sourceUrl) ?>"
                 alt="<?= e($mediaAlt) ?>"
                 loading="lazy">
        </picture>

        <span class="ra-image-tile-badge"
              data-ra-primary-badge
              <?= $isPrimary ? '' : 'hidden' ?>>
            <i class="bi bi-star-fill" aria-hidden="true"></i> Primary
        </span>
    </div>

    <div class="ra-image-tile-body">

        <div class="ra-image-tile-field">
            <label class="ra-image-tile-checkbox">
                <input type="radio"
                       name="images[<?= $imageId ?>][is_primary]"
                       value="1"
                       data-ra-primary-radio
                       <?= $isPrimary ? 'checked' : '' ?>>
                <span>Set as primary</span>
            </label>
        </div>

        <div class="ra-image-tile-field">
            <label for="ra-image-alt-<?= $imageId ?>"
                   class="ra-image-tile-label">
                Alt text
            </label>
            <input type="text"
                   id="ra-image-alt-<?= $imageId ?>"
                   name="images[<?= $imageId ?>][alt_text]"
                   class="form-control form-control-sm"
                   maxlength="255"
                   value="<?= e($altText) ?>"
                   placeholder="Describe this image">
        </div>

        <input type="hidden"
               name="images[<?= $imageId ?>][sort_order]"
               value="<?= (int) $image->sortOrder ?>"
               data-ra-sort-order-input>

        <div class="ra-image-tile-actions">
            <button type="button"
                    class="ra-btn ra-btn-outline ra-btn-sm"
                    data-ra-remove-tile>
                <i class="bi bi-trash me-1" aria-hidden="true"></i>
                Remove
            </button>
        </div>

    </div>
</div>
