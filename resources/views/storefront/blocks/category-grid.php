<?php
/**
 * @var array<string,mixed> $config
 * @var array<int, \CorporateAid\Commerce\Models\Category> $categories
 */
$title = $config['title'] ?? 'Shop by Category';
?>
<section class="sf-section" style="padding: 4rem 2rem; background: var(--sf-surface-1);">
    <div style="max-width: 1200px; margin: 0 auto;">
        <h2 style="font-family: var(--sf-font-display); font-size: 2rem; text-align: center; margin-bottom: 2rem;"><?= e($title) ?></h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
            <?php foreach ($categories as $cat): ?>
                <a href="/categories/<?= e($cat->slug) ?>" style="display: block; text-align: center; text-decoration: none; color: inherit;">
                    <div style="aspect-ratio: 1; background: var(--sf-surface-2); border-radius: 8px; overflow: hidden; margin-bottom: 1rem;">
                        <?php if ($cat->imageFileId): ?>
                            <img src="/media/<?= $cat->imageFileId ?>/md.jpg" alt="<?= e($cat->name) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php endif; ?>
                    </div>
                    <h3 style="font-size: 1.1rem; font-weight: 600;"><?= e($cat->name) ?></h3>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
