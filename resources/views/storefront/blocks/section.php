<?php
/**
 * Section block — a structural container.
 *
 * Renders a full-width <section> with the rendered HTML of
 * its children inside. The children_html string is set by
 * BlockRenderer::render() for any block type whose registry
 * entry declares accepts_children: true.
 *
 * Section has no per-instance config today. Config fields
 * (background, padding, container width) ship with the
 * layout engine in a later sub-stage.
 *
 * @var array<string,mixed> $config
 * @var string $children_html  Rendered children (may be empty)
 */
$childrenHtml = $children_html ?? '';
?>
<section class="sf-section-block">
    <?= $childrenHtml ?>
</section>
