<?php
/**
 * Generic result page for the newsletter confirm and
 * unsubscribe flows.
 *
 * Context:
 *   $title   string   page heading
 *   $ok      bool     true for success, false for any failure
 *   $message string   the message to display
 *
 * The page is deliberately neutral. Every failure — bad
 * token, expired token, wrong scope, missing subscriber,
 * already unsubscribed — renders the same shape. An
 * attacker probing the endpoint cannot distinguish
 * outcomes.
 */
$title   = $title   ?? 'Newsletter';
$ok      = $ok      ?? false;
$message = $message ?? '';
?>
<div class="sf-container" style="max-width:560px;margin:4rem auto;padding:0 1rem;">
    <div style="background:#fff;border:1px solid #e5e1d8;border-radius:12px;padding:2rem 2rem 1.5rem;text-align:center;">
        <div style="font-size:2.5rem;color:<?= $ok ? '#0f4c81' : '#a86c2c' ?>;margin-bottom:.75rem;">
            <i class="bi <?= $ok ? 'bi-check-circle' : 'bi-exclamation-circle' ?>" aria-hidden="true"></i>
        </div>
        <h1 style="font-size:1.35rem;margin:0 0 .75rem;color:#0f172a;">
            <?= e($title) ?>
        </h1>
        <p style="color:#475569;margin:0 0 1.5rem;line-height:1.55;">
            <?= e($message) ?>
        </p>
        <a href="/" style="display:inline-block;background:#0f4c81;color:#fff;text-decoration:none;padding:.7rem 1.4rem;border-radius:8px;font-weight:600;">
            Back to the store
        </a>
    </div>
</div>
