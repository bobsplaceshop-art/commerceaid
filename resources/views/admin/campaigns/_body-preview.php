<?php
/**
 * Campaign body preview iframe.
 *
 * Context:
 *   $campaignId  int
 *
 * The iframe points at the raw preview endpoint. The raw
 * document is a standalone email HTML file. It renders on
 * the recipient's email-client defaults and has no access
 * to the admin theme, the admin CSS, or the admin's
 * cookies (sandbox without allow-same-origin).
 *
 * campaign-preview.js listens for the raw document's
 * height postMessage and sets the iframe height so the
 * whole email renders as a fixed full document.
 */
$campaignId = (int) ($campaignId ?? 0);
?>
<iframe
    data-campaign-preview
    class="campaign-preview-iframe"
    sandbox="allow-scripts"
    src="/commerce/ca-admin/campaigns/<?= $campaignId ?>/preview?raw=1"
    title="Campaign body preview"
></iframe>
