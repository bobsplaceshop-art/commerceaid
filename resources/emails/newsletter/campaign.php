<?php
/**
 * Campaign email wrapper.
 *
 * Variables:
 *   $storeName        string
 *   $storeAddress     string  (physical address, required by UWG § 7)
 *   $subject          string
 *   $preheader        string
 *   $bodyHtml         string  (from the campaign record)
 *   $unsubscribeUrl   string  (signed)
 *   $subscriberEmail  string
 *   $trackingPixelUrl string|null  (when tracking enabled)
 */
$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= $e($subject ?? '') ?></title>
</head>
<body style="font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:15px;color:#111;line-height:1.6;max-width:600px;margin:0 auto;padding:24px;">

    <?php if (!empty($preheader)): ?>
        <div style="display:none;font-size:1px;color:#fff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
            <?= $e($preheader) ?>
        </div>
    <?php endif; ?>

    <header style="border-bottom:1px solid #e5e1d8;padding-bottom:1rem;margin-bottom:1.5rem;">
        <div style="font-weight:600;font-size:1.05rem;"><?= $e($storeName ?? '') ?></div>
    </header>

    <main>
        <?= $bodyHtml ?? '' ?>
    </main>

    <?php if (!empty($trackingPixelUrl)): ?>
        <img src="<?= $e($trackingPixelUrl) ?>" width="1" height="1" alt="" style="display:block;">
    <?php endif; ?>

    <hr style="border:none;border-top:1px solid #e5e1d8;margin:2rem 0 1rem;">

    <footer style="color:#666;font-size:.78rem;line-height:1.55;">
        <p style="margin:0 0 .5rem;">
            <?= $e($storeName ?? '') ?>
            <?php if (!empty($storeAddress)): ?>
                <br><?= $e($storeAddress) ?>
            <?php endif; ?>
        </p>
        <p style="margin:0;">
            You are receiving this email at <?= $e($subscriberEmail ?? '') ?>
            because you subscribed to our newsletter.
            <a href="<?= $e($unsubscribeUrl ?? '#') ?>" style="color:#666;">Unsubscribe</a>.
        </p>
    </footer>
</body>
</html>
