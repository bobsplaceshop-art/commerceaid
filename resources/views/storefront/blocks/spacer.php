<?php
/**
 * Spacer block. Renders vertical whitespace.
 *
 * @var array<string,mixed> $config
 */
$height = (int) ($config['height'] ?? 48);
if ($height < 0) {
    $height = 0;
}
?>
<div style="height: <?= $height ?>px;" aria-hidden="true"></div>
