<?php
/**
 * Legal page (Impressum, AGB, Widerrufsbelehrung, Datenschutz).
 *
 * Context (from CheckoutController::legalPage):
 *   $page  LegalPage
 *
 * Body is HTML rendered as-is. The admin edits it directly.
 * See docs/stages/008e_checkout-plan.md for the compliance
 * requirements.
 */
$page = $page ?? null;

if ($page === null) {
    echo '<div class="sf-container"><p>Page not found.</p></div>';
    return;
}
?>
<div class="sf-legal" data-sf-page="legal">

    <div class="sf-container">
        <article class="sf-legal-article">
            <h1 class="sf-legal-title"><?= e($page->title) ?></h1>

            <?php if ($page->updatedAt !== null): ?>
                <p class="muted small">
                    Last updated
                    <?= e(date('F j, Y', strtotime($page->updatedAt))) ?>
                </p>
            <?php endif; ?>

            <div class="sf-legal-body">
                <?= $page->body ?>
            </div>

            <p class="muted small mt-4">
                <a href="/" class="sf-link">Back to shop</a>
            </p>
        </article>
    </div>

</div>
