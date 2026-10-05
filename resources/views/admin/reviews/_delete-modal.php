<?php
/**
 * Review delete confirmation modal.
 *
 * Opened via JS: RA_DeleteReview.open(id, authorName).
 * The modal only confirms the id and shows the author name.
 * Submission is a standard POST to the delete route.
 *
 * @var string $status  the active filter; used as return_status
 */
$status = $status ?? 'pending';
?>
<div id="ra-review-delete-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_DeleteReview.close()"></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-review-delete-title">
        <div class="ra-modal-header">
            <h3 id="ra-review-delete-title" class="h6 mb-0">Delete review</h3>
            <button type="button" class="ra-icon-btn" onclick="RA_DeleteReview.close()" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3">
                Delete the review by
                <strong id="ra-review-delete-author">this customer</strong>?
            </p>
            <div class="ra-alert ra-alert-warning mt-3">
                This action cannot be undone. The review will be permanently removed and will no longer count toward the product's rating.
            </div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" onclick="RA_DeleteReview.close()">
                Cancel
            </button>
            <form method="POST" id="ra-review-delete-form" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="return_status" value="<?= e($status) ?>">
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
window.RA_DeleteReview = (function () {
    'use strict';

    var modalId = 'ra-review-delete-modal';
    var authorEl, formEl;

    function init() {
        authorEl = document.getElementById('ra-review-delete-author');
        formEl   = document.getElementById('ra-review-delete-form');
    }

    function open(id, author) {
        if (!authorEl) init();

        document.getElementById(modalId).style.display = 'flex';
        authorEl.textContent = author || 'this customer';
        formEl.action = '/commerce/ca-admin/reviews/' + id + '/delete';
    }

    function close() {
        document.getElementById(modalId).style.display = 'none';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    return { open: open, close: close };
})();
</script>
