<?php
/**
 * @var array<string,mixed> $config
 * @var array<int, \CorporateAid\Commerce\Models\Category> $categories
 */
$title = $config['title'] ?? 'Shop by Category';
if (empty($categories)) {
    return;
}
?>
<section class="sf-section sf-category-grid-section" style="padding: 4rem 2rem; background: var(--sf-surface-1);">
    <div style="max-width: 1200px; margin: 0 auto;">
        <h2 style="font-family: var(--sf-font-display); font-size: 2rem; text-align: center; margin-bottom: 2.5rem;"><?= e($title) ?></h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1.5rem;">
            <?php foreach ($categories as $cat): ?>
                <a href="/categories/<?= e($cat->slug) ?>" style="display: flex; flex-direction: column; align-items: center; text-decoration: none; color: inherit; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
                    <div style="width: 120px; height: 120px; background: var(--sf-surface-2); border-radius: 50%; overflow: hidden; margin-bottom: 0.75rem; border: 2px solid var(--sf-border);">
                        <?php if ($cat->imageFileId): ?>
                            <img src="/media/<?= $cat->imageFileId ?>/md.jpg"
                                 alt="<?= e($cat->name) ?>"
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--sf-muted);">
                                <i class="bi bi-grid" style="font-size: 2rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                        <span style="font-size: 0.95rem; font-weight: 600; text-align: center;"><?= e($cat->name) ?></span>
                        <span style="width: 22px; height: 22px; background: var(--sf-accent); color: #fff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem;">
                            <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
