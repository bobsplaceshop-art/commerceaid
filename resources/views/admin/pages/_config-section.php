<?php
/**
 * One config section. Renders a collapsible group of fields.
 *
 * @var string $sectionKey  e.g. 'content', 'layout', 'style', 'advanced'
 * @var string $title       display title
 * @var string $icon        Bootstrap Icons class
 * @var string $bodyHtml    pre-rendered fields markup
 * @var int    $count       number of fields in the section
 */
$sectionKey = (string) ($sectionKey ?? 'content');
$title      = (string) ($title      ?? 'Content');
$icon       = (string) ($icon       ?? 'bi-text-paragraph');
$bodyHtml   = (string) ($bodyHtml   ?? '');
$count      = (int)    ($count      ?? 0);
$collapsed  = in_array($sectionKey, ['advanced'], true);
?>
<div class="ra-config-section<?= $collapsed ? ' is-collapsed' : '' ?>"
     data-section="<?= e($sectionKey) ?>">
    <button type="button"
            class="ra-config-section-header"
            onclick="RA_ConfigSections.toggle(this)">
        <i class="bi <?= e($icon) ?> me-2" aria-hidden="true"></i>
        <span class="ra-config-section-title"><?= e($title) ?></span>
        <span class="ra-config-section-count"><?= (int) $count ?></span>
        <i class="bi bi-chevron-down ra-config-section-chevron" aria-hidden="true"></i>
    </button>
    <div class="ra-config-section-body">
        <?= $bodyHtml ?>
    </div>
</div>
