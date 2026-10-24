<?php
/**
 * Container block — a nested structural wrapper.
 *
 * Renders a <div> with the rendered HTML of its children
 * inside. Used inside a Section to group related elements.
 *
 * Container has no per-instance config today. Layout
 * properties (flex direction, gap, padding) ship with the
 * layout engine in a later sub-stage.
 *
 * @var array<string,mixed> $config
 * @var string $children_html  Rendered children (may be empty)
 */
$childrenHtml = $children_html ?? '';
?>
<div class="sf-container-block">
    <?= $childrenHtml ?>
</div>
