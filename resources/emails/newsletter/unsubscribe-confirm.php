<?php
/**
 * Unsubscribe confirmation email.
 *
 * Variables:
 *   $storeName      string
 *   $subscriberEmail string
 *   $resubscribeUrl  string
 */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Unsubscribed</title>
</head>
<body style="font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:15px;color:#111;line-height:1.6;max-width:600px;margin:0 auto;padding:24px;">
    <h1 style="font-size:1.35rem;margin:0 0 1rem;">You have been unsubscribed</h1>

    <p><strong><?= htmlspecialchars($subscriberEmail ?? '', ENT_QUOTES, 'UTF-8') ?></strong> has been removed from the newsletter list for <strong><?= htmlspecialchars($storeName ?? 'this store', ENT_QUOTES, 'UTF-8') ?></strong>.</p>

    <p>You will not receive any more campaigns. Transactional emails (order confirmations, password resets) are unaffected.</p>

    <p style="color:#666;font-size:.85rem;">
        Changed your mind? You can re-subscribe at any time:
        <a href="<?= htmlspecialchars($resubscribeUrl ?? '#', ENT_QUOTES, 'UTF-8') ?>">subscribe again</a>.
    </p>

    <hr style="border:none;border-top:1px solid #e5e1d8;margin:1.5rem 0;">
    <p style="color:#666;font-size:.8rem;margin:0;">
        <?= htmlspecialchars($storeName ?? 'The store team', ENT_QUOTES, 'UTF-8') ?>
    </p>
</body>
</html>
