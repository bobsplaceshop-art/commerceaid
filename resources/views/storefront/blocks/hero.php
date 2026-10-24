<?php
/**
 * @var array<string,mixed> $config
 * @var string|null $bg_url
 */
$bgUrl = $bg_url ?? '';
$style = $bgUrl ? "background-image: url('{$bgUrl}');" : "background-color: var(--sf-surface-2);";
?>
<section class="sf-hero" style="<?= e($style) ?> background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; text-align: center;">
    <div class="sf-hero-content" style="max-width: 800px; background: rgba(255,255,255,0.85); padding: 3rem; border-radius: 8px;">
        <h1 style="font-family: var(--sf-font-display); font-size: 3rem; margin-bottom: 1rem;"><?= e($config['headline'] ?? '') ?></h1>
        <?php if (!empty($config['subheadline'])): ?>
            <p style="font-size: 1.2rem; margin-bottom: 2rem;"><?= e($config['subheadline']) ?></p>
        <?php endif; ?>
        <?php if (!empty($config['cta_text'])): ?>
            <a href="<?= e($config['cta_url'] ?? '/products') ?>" class="sf-btn sf-btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                <?= e($config['cta_text']) ?>
            </a>
        <?php endif; ?>
    </div>
</section>
