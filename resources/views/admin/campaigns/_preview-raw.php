<?php
/**
 * Campaign raw preview document.
 *
 * Loaded by the admin iframe via
 *   GET /commerce/ca-admin/campaigns/{id}/preview?raw=1
 *
 * Renders the exact same HTML the recipient's email client
 * receives, on the email-client defaults (white background,
 * dark text, 600px max width, 24px padding). The only
 * difference from the real send: the unsubscribe link and
 * the tracking pixel use placeholders. Neither affects the
 * visual rendering.
 *
 * A ResizeObserver inside this document posts its content
 * height to the parent so the admin iframe can autogrow
 * without scrollbars. The measurement uses
 * document.body.scrollHeight, which does not depend on the
 * iframe's own viewport height and therefore cannot loop.
 *
 * Context:
 *   $campaign  EmailCampaign
 *   $storeName string
 */
$campaign  = $campaign  ?? null;
$storeName = $storeName ?? '';
if ($campaign === null) return;

$storeAddress    = '';        // shown in the admin card, not in the raw preview
$unsubscribeUrl  = '#';       // placeholder
$subscriberEmail = 'preview@example.test';
$trackingPixelUrl = null;

$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $e($campaign->subject) ?></title>
</head>
<body style="font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:15px;color:#111;line-height:1.6;max-width:600px;margin:0 auto;padding:24px;background:#fff;">

    <?php if ($campaign->preheader !== null && $campaign->preheader !== ''): ?>
        <div style="display:none;font-size:1px;color:#fff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
            <?= $e($campaign->preheader) ?>
        </div>
    <?php endif; ?>

    <header style="border-bottom:1px solid #e5e1d8;padding-bottom:1rem;margin-bottom:1.5rem;">
        <div style="font-weight:600;font-size:1.05rem;"><?= $e($storeName) ?></div>
    </header>

    <main>
        <?= $campaign->bodyHtml ?>
    </main>

    <hr style="border:none;border-top:1px solid #e5e1d8;margin:2rem 0 1rem;">

    <footer style="color:#666;font-size:.78rem;line-height:1.55;">
        <p style="margin:0 0 .5rem;">
            <?= $e($storeName) ?>
        </p>
        <p style="margin:0;">
            You are receiving this email at <?= $e($subscriberEmail) ?>
            because you subscribed to our newsletter.
            <a href="<?= $e($unsubscribeUrl) ?>" style="color:#666;">Unsubscribe</a>.
        </p>
    </footer>

    <script>
    (function () {
        'use strict';
        var last = 0;
        var pending = false;

        function post() {
            pending = false;
            var h = document.body.scrollHeight;
            if (h === last) return;
            last = h;
            try {
                parent.postMessage(
                    { type: 'campaign-preview:height', height: h },
                    '*'
                );
            } catch (e) {}
        }

        function schedule() {
            if (pending) return;
            pending = true;
            requestAnimationFrame(post);
        }

        if (window.ResizeObserver) {
            new ResizeObserver(schedule).observe(document.body);
        }
        window.addEventListener('load', schedule);
        window.addEventListener('resize', schedule);
        setTimeout(schedule, 100);
    })();
    </script>
</body>
</html>
