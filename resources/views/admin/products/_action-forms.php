<?php
/**
 * Hidden action forms for the product editor.
 *
 * Included by edit.php (via the sidebar) and variants.php. The
 * sidebar buttons and the variants page both reference these
 * form IDs via document.getElementById(...).submit(). They
 * must exist on every page that shows the action buttons.
 *
 * Contract:
 *   $product   Product|null   required in practice - if null,
 *                             the partial renders nothing
 *
 * Note on $mode: earlier versions of this partial required
 * $mode === 'edit' as a proxy for "is this an edit page?".
 * That broke the variants page, which has no $mode. The
 * partial's actual requirement is a product to act on.
 * Callers may still pass $mode - it is silently ignored.
 */
$product = $product ?? null;

// The forms act on a product. If there is no product in scope,
// there is nothing to publish, archive, or restore. The $mode
// variable is not part of this partial's contract - it was a
// proxy for "is this an edit page?" that broke when the
// variants page (which has no $mode) included the partial.
if ($product === null) {
    return;
}
?>
<form method="POST"
      action="/commerce/ca-admin/products/<?= (int) $product->id ?>/publish"
      id="ra-action-publish"
      class="d-none">
    <?= csrf_field() ?>
</form>

<form method="POST"
      action="/commerce/ca-admin/products/<?= (int) $product->id ?>/unpublish"
      id="ra-action-unpublish"
      class="d-none">
    <?= csrf_field() ?>
</form>

<form method="POST"
      action="/commerce/ca-admin/products/<?= (int) $product->id ?>/archive"
      id="ra-action-archive"
      class="d-none">
    <?= csrf_field() ?>
</form>

<form method="POST"
      action="/commerce/ca-admin/products/<?= (int) $product->id ?>/restore"
      id="ra-action-restore"
      class="d-none">
    <?= csrf_field() ?>
</form>
