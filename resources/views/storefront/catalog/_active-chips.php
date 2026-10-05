<?php
/**
 * Active filter chips.
 *
 * Shared between the /products catalog page and the
 * /categories/{slug} category detail page.
 *
 * Callers pass:
 *   $activeChips     array<int,array{key:string, label:string}>
 *   $clearFilterUrl  callable(string): string   URL that removes one filter
 *   $clearAllUrl     string                     URL that removes every filter
 *
 * Renders nothing when $activeChips is empty.
 */

$activeChips    = $activeChips    ?? [];
$clearFilterUrl = $clearFilterUrl ?? null;
$clearAllUrl    = $clearAllUrl    ?? '/products';

if (empty($activeChips) || $clearFilterUrl === null) {
    return;
}
?>

<div class="sf-catalog-active-filters" data-sf-catalog-block="active-filters">
    <?php foreach ($activeChips as $chip): ?>
        <a href="<?= e($clearFilterUrl($chip['key'])) ?>" class="sf-chip">
            <span><?= e($chip['label']) ?></span>
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </a>
    <?php endforeach; ?>
    <a href="<?= e($clearAllUrl) ?>" class="sf-chip sf-chip-clear-all">
        Clear all
    </a>
</div>
