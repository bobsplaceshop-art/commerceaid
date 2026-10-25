<?php
/**
 * @var array<string,mixed> $config
 * @var string|null $bg_url
 */
$bgUrl = $bg_url ?? '';
?>
<section class="sf-promo" style="padding: 4rem 2rem;">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; background: var(--sf-surface-2); border-radius: 8px; overflow: hidden; min-height: 400px;">
        <div style="flex: 1; padding: 4rem; display: flex; flex-direction: column; justify-content: center;">
            <h2 style="font-family: var(--sf-font-display); font-size: 2.5rem; margin-bottom: 2rem;"><?= e($config['title'] ?? '') ?></h2>
            <a href="<?= e($config['cta_url'] ?? '#') ?>" class="sf-btn sf-btn-primary" style="align-self: flex-start;"><?= e($config['cta_text'] ?? 'Shop Now') ?></a>
        </div>
        <div style="flex: 1; background-image: url('<?= e($bgUrl) ?>'); background-size: cover; background-position: center;"></div>
    </div>
</section>
