<?php
/**
 * Storefront popup banner.
 *
 * Only rendered when StorefrontController::resolvePopupEnabled()
 * returns true. This partial trusts that gate; the cookie-based
 * dismissal is handled client-side by storefront-popup.js.
 *
 * @var \CorporateAid\Commerce\Repositories\StoreSettingsRepository $settings
 */
$headline = trim($settings->string('popup.headline', ''));
$body     = trim($settings->string('popup.body', ''));
$ctaText  = trim($settings->string('popup.cta_text', ''));
$ctaUrl   = trim($settings->string('popup.cta_url', ''));
$imageId  = $settings->int('popup.image_file_id', 0);

// Resolve the file's UUID and format. The media serve
// route uses the UUID, not the numeric file id, so we
// look up the file once and grab both.
$imageUuid = '';
$imageExt  = 'jpg';
if ($imageId > 0) {
    try {
        $media = app(\CorporateAid\Commerce\Repositories\MediaRepository::class);
        $item  = $media->findById($imageId);
        if ($item !== null) {
            $imageUuid = (string) $item->file->uuid;
            if (str_contains((string) $item->file->mimeType, 'webp')) {
                $imageExt = 'webp';
            }
        }
    } catch (\Throwable) {}
}

// Cookie name derived from headline, so changing the headline
// starts a new campaign (all visitors see it fresh).
$cookieKey = 'sf_popup_' . substr(md5($headline), 0, 8);
?>
<div id="sf-popup-modal"
     class="sf-popup-overlay"
     data-sf-popup-cookie="<?= e($cookieKey) ?>"
     data-sf-popup-cookie-days="365"
     style="display: none;"
     aria-hidden="true">
    <div class="sf-popup-content" role="dialog" aria-modal="true" aria-labelledby="sf-popup-headline">
        <button type="button" class="sf-popup-close" id="sf-popup-close" aria-label="Close">&times;</button>
        <?php if ($imageUuid !== ''): ?>
            <div class="sf-popup-image">
                <img src="/media/<?= e($imageUuid) ?>/lg.<?= e($imageExt) ?>" alt="">
            </div>
        <?php endif; ?>
        <div class="sf-popup-body">
            <h2 class="sf-popup-headline" id="sf-popup-headline"><?= e($headline) ?></h2>
            <?php if ($body !== ''): ?>
                <p class="sf-popup-text"><?= e($body) ?></p>
            <?php endif; ?>
            <?php if ($ctaText !== '' && $ctaUrl !== ''): ?>
                <a href="<?= e($ctaUrl) ?>" class="sf-btn sf-btn-primary sf-popup-btn">
                    <?= e($ctaText) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
