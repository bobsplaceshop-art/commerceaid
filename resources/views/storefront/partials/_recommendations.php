<?php
if (empty($recommendations)) return;
?>

<section class="sf-section sf-recommendations-section" style="padding-top: 3rem; padding-bottom: 3rem; background: var(--sf-cream);">
    <div class="sf-container">
        <div class="sf-section-header" style="margin-bottom: 2rem; text-align: center;">
            <h2 class="sf-section-title" style="font-family: var(--sf-font-display); font-size: 2rem;">You May Also Like</h2>
            <p class="sf-section-subtitle" style="color: var(--sf-muted);">Handpicked just for you.</p>
        </div>
        
        <div class="sf-product-grid">
            <?php foreach ($recommendations as $p): ?>
                <?= view_partial('storefront/catalog/_product-card', ['product' => $p]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
