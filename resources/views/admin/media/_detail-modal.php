<?php
/**
 * Media detail / metadata editor modal.
 *
 * Opened via JS: RA_MediaDetail.open(id, name, altText)
 *
 * Two fields: alt text and credit. The credit column exists
 * for UrhG § 13 attribution compliance. It is optional.
 */
?>
<div id="ra-media-detail-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-media-detail-close></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-media-detail-title">
        <form method="POST" id="ra-media-detail-form" action="" style="margin:0;">
            <?= csrf_field() ?>

            <div class="ra-modal-header">
                <h3 id="ra-media-detail-title" class="h6 mb-0">Edit file details</h3>
                <button type="button" class="ra-icon-btn" data-ra-media-detail-close aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="ra-modal-body">
                <p class="muted small mb-3">
                    <strong id="ra-media-detail-name">this file</strong>
                </p>

                <div class="mb-3">
                    <label for="ra-media-detail-alt" class="form-label small">Alt text</label>
                    <input type="text"
                           id="ra-media-detail-alt"
                           name="alt_text"
                           class="form-control form-control-sm"
                           maxlength="500"
                           placeholder="Describe the image for accessibility">
                    <div class="form-text">
                        Used by screen readers and when the image cannot load.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="ra-media-detail-credit" class="form-label small">Credit</label>
                    <input type="text"
                           id="ra-media-detail-credit"
                           name="credit"
                           class="form-control form-control-sm"
                           maxlength="255"
                           placeholder="Photographer or source">
                    <div class="form-text">
                        Optional. Recorded for attribution (UrhG § 13).
                    </div>
                </div>
            </div>

            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-ra-media-detail-close>
                    Cancel
                </button>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>
