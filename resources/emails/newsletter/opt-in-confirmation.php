<?php
/**
 * Double opt-in confirmation email.
 *
 * Variables (extracted into scope by EmailService::sendTemplate):
 *   $storeName     string
 *   $confirmUrl    string
 *   $subscriberEmail string
 *   $unsubscribeUrl  string  (the customer's signed
 *                              unsubscribe URL; the
 *                              "did not ask to subscribe"
 *                              footer link uses it)
 */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Confirm your subscription</title>
</head>
<body style="font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:15px;color:#111;line-height:1.6;max-width:600px;margin:0 auto;padding:24px;">
    <h1 style="font-size:1.35rem;margin:0 0 1rem;">Confirm your subscription</h1>

    <p>You asked to subscribe to updates from <strong><?= htmlspecialchars($storeName ?? 'this store', ENT_QUOTES, 'UTF-8') ?></strong>.</p>

    <p>Click the button below to confirm. Until you do, you will not receive any campaigns.</p>

    <p style="margin:1.5rem 0;">
        <a href="<?= htmlspecialchars($confirmUrl ?? '#', ENT_QUOTES, 'UTF-8') ?>"
           style="display:inline-block;background:#0f4c81;color:#fff;padding:.7rem 1.2rem;border-radius:6px;text-decoration:none;font-weight:600;">
            Confirm subscription
        </a>
    </p>

    <p style="color:#666;font-size:.85rem;">
        If the button does not work, copy this link into your browser:<br>
        <span style="word-break:break-all;"><?= htmlspecialchars($confirmUrl ?? '', ENT_QUOTES, 'UTF-8') ?></span>
    </p>

    <p style="color:#666;font-size:.85rem;">
        You are receiving this email because
        <strong><?= htmlspecialchars($subscriberEmail ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
        was entered into the newsletter form on our site. If this was a mistake,
        you can ignore this message. No further email will be sent unless you click the link above.
    </p>

    <p style="color:#666;font-size:.85rem;">
        If you did not ask to subscribe, you can
        <a href="<?= htmlspecialchars($unsubscribeUrl ?? '#', ENT_QUOTES, 'UTF-8') ?>" style="color:#666;">unsubscribe entirely</a>.
    </p>

    <hr style="border:none;border-top:1px solid #e5e1d8;margin:1.5rem 0;">
    <p style="color:#666;font-size:.8rem;margin:0;">
        <?= htmlspecialchars($storeName ?? 'The store team', ENT_QUOTES, 'UTF-8') ?>
    </p>
</body>
</html>
