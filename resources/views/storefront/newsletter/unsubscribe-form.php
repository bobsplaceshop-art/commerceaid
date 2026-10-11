<?php
/**
 * Unsubscribe confirmation form.
 *
 * Context:
 *   $title  string
 *   $token  string  (the raw signed token from the URL)
 *
 * The GET on /newsletter/unsubscribe/{token} renders this
 * page. The POST on the same URL performs the unsubscribe.
 * The two-step flow exists because mail scanners and
 * antivirus gateways auto-click every link in an email; if
 * GET unsubscribed directly, every scanned email would
 * silently remove the recipient.
 *
 * The reason field is optional. Recording a reason is
 * polite but never required, and the field must never
 * become a funnel for support requests.
 */
$title = $title ?? 'Unsubscribe';
$token = $token ?? '';
?>
<div class="sf-container" style="max-width:560px;margin:4rem auto;padding:0 1rem;">
    <div style="background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:2rem;">

        <h1 style="font-size:1.35rem;margin:0 0 .75rem;color:#0f172a;">
            <?= e($title) ?>
        </h1>

        <p style="color:#475569;line-height:1.55;margin:0 0 1.5rem;">
            Click below to stop receiving newsletter campaigns from this store.
            Transactional emails (order confirmations, password resets) are
            unaffected.
        </p>

        <form method="POST"
              action="/newsletter/unsubscribe/<?= e(urlencode($token)) ?>"
              style="margin:0;">
            <?= csrf_field() ?>

            <label for="newsletter-unsub-reason"
                   style="display:block;font-weight:600;color:#0f172a;margin:0 0 .35rem;font-size:.9rem;">
                Reason <span style="color:#94a3b8;font-weight:400;">(optional)</span>
            </label>
            <textarea id="newsletter-unsub-reason"
                      name="reason"
                      rows="2"
                      maxlength="255"
                      placeholder="Too many emails, no longer interested, etc."
                      style="width:100%;padding:.6rem .75rem;border:1px solid #cbd5e1;border-radius:6px;font-family:inherit;font-size:.95rem;resize:vertical;margin:0 0 1.25rem;"></textarea>

            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
                <button type="submit"
                        style="background:#a86c2c;color:#fff;border:none;padding:.7rem 1.4rem;border-radius:8px;font-weight:600;font-size:.95rem;cursor:pointer;font-family:inherit;">
                    Unsubscribe
                </button>
                <a href="/"
                   style="color:#475569;text-decoration:none;font-size:.95rem;">
                    Cancel
                </a>
            </div>
        </form>

    </div>
</div>
