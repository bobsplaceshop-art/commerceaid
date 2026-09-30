<?php
/**
 * Media delete confirmation modal.
 *
 * Opened via JS: RA_MediaDelete.open(id, name, usageCategory)
 *
 * The server-side delete action has its own usage guard. This
 * modal only warns the operator. If they proceed on a file
 * that is in use, they get a flash error back with the list
 * of references.
 */
?>
<div id="ra-media-delete-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-media-delete-close></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-media-delete-title">
        <div class="ra-modal-header">
            <h3 id="ra-media-delete-title" class="h6 mb-0">Delete file</h3>
            <button type="button" class="ra-icon-btn" data-ra-media-delete-close aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3">
                Delete <strong id="ra-media-delete-name">this file</strong>?
            </p>
            <div id="ra-media-delete-usage" class="muted small mb-3"></div>
            <div class="ra-alert ra-alert-warning">
                Deleted files are removed from the storefront. Files still
                referenced by a store logo, product image, or other entity
                cannot be deleted here until they are unlinked.
            </div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" data-ra-media-delete-close>
                Cancel
            </button>
            <form method="POST" id="ra-media-delete-form" action="" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>
