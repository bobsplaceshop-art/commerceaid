<?php
/**
 * Campaign preview. Standalone page.
 *
 * The page obeys the admin's active theme exactly like every
 * other page in the application. The theme is resolved client-
 * side from localStorage['ra-theme'] in the pre-paint script
 * below, matching the pattern in resources/views/errors/*.php
 * and layouts/app.php.
 *
 * The campaign body is merchant-authored HTML. It sits inside a
 * themed card. The merchant's own inline styles on the body
 * (colors, fonts) are honoured — the sandbox wrapper does not
 * override them. But the wrapper (header, footer, frame) uses
 * the theme variables so the page reads as part of the admin
 * surface regardless of the theme.
 *
 * Context:
 *   $campaign EmailCampaign
 */
$campaign = $campaign ?? null;
if ($campaign === null) return;
?>
<!doctype html>
<html lang="en" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview: <?= e($campaign->subject) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= versioned_asset('css/main.css') ?>" rel="stylesheet">

    <?php /* Pre-paint theme resolution - matches layouts/app.php. */ ?>
    <script>
    (function () {
        try {
            var stored = localStorage.getItem('ra-theme');
            if (stored && ['dark', 'light', 'system'].indexOf(stored) !== -1) {
                document.documentElement.setAttribute('data-theme', stored);
            }
        } catch (e) {}
    })();
    </script>

    <style>
        /* Page shell. Uses only theme variables so the page
           follows the active theme. */
        body {
            background: var(--ra-bg);
            color: var(--ra-text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Informational banner. Uses surface-2 and a border
           so it reads as a notice in both themes, not as a
           primary-action bar. */
        .preview-banner {
            background: var(--ra-surface-2);
            color: var(--ra-text-soft, var(--ra-muted));
            border-bottom: 1px solid var(--ra-border);
            padding: .7rem 1rem;
            text-align: center;
            font-size: .85rem;
            line-height: 1.4;
        }
        .preview-banner .bi {
            margin-right: .35rem;
            color: var(--ra-text, var(--ra-text-soft));
        }

        .preview-wrap {
            max-width: 640px;
            margin: 0 auto;
            padding: 1.5rem 1.25rem 3rem;
        }

        .preview-header {
            padding: 1rem 0;
            border-bottom: 1px solid var(--ra-border);
        }
        .preview-header-name {
            font-weight: 600;
            color: var(--ra-text-strong);
        }
        .preview-header-meta {
            font-size: .85rem;
            color: var(--ra-text-soft, var(--ra-muted));
            margin-top: .25rem;
        }

        /* The body card. This is the campaign HTML's home. It
           uses the theme's surface colour so the card blends
           with the page, and the body text uses the theme's
           text colour. */
        .preview-body-card {
            background: var(--ra-surface);
            border: 1px solid var(--ra-border);
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            color: var(--ra-text);
        }
        .preview-body-card a {
            color: var(--ra-link);
            text-decoration: underline;
        }
        .preview-body-card h1,
        .preview-body-card h2,
        .preview-body-card h3,
        .preview-body-card h4 {
            color: var(--ra-text-strong);
            margin-top: 1rem;
            margin-bottom: .5rem;
        }
        .preview-body-card p,
        .preview-body-card ul,
        .preview-body-card ol {
            margin: 0 0 .85rem;
        }
        .preview-body-card img {
            max-width: 100%;
            height: auto;
        }

        .preview-footer {
            padding: 1rem 0;
            border-top: 1px solid var(--ra-border);
            font-size: .8rem;
            line-height: 1.55;
            color: var(--ra-text-soft, var(--ra-muted));
        }
        .preview-footer a {
            color: var(--ra-text-soft, var(--ra-muted));
        }

        .preview-note {
            background: var(--ra-surface-2);
            border: 1px solid var(--ra-border);
            border-radius: 6px;
            padding: .85rem 1rem;
            margin-top: 1.5rem;
            font-size: .82rem;
            color: var(--ra-text-soft, var(--ra-muted));
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="preview-banner">
        <i class="bi bi-eye" aria-hidden="true"></i>
        Preview — the body below is what your campaign HTML contains.
    </div>

    <div class="preview-wrap">
        <div class="preview-header">
            <div class="preview-header-name">Anaya Global</div>
            <div class="preview-header-meta">
                <strong>Subject:</strong> <?= e($campaign->subject) ?>
            </div>
            <?php if ($campaign->preheader !== null && $campaign->preheader !== ''): ?>
                <div class="preview-header-meta">
                    <strong>Preheader:</strong> <?= e($campaign->preheader) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="preview-body-card">
            <?= $campaign->bodyHtml ?>
        </div>

        <div class="preview-footer">
            <p style="margin:0 0 .5rem;">
                Anaya Global<br>
                Global Commerce Platform, Nairobi, Kenya
            </p>
            <p style="margin:0;">
                You are receiving this email because you subscribed
                to our newsletter.
                <a href="#">Unsubscribe</a>.
            </p>
        </div>

        <div class="preview-note">
            <strong>Note:</strong> This preview shows the campaign body
            rendered inside the admin theme. Email clients render HTML
            differently — Gmail, Outlook, Apple Mail, and mobile clients
            each apply their own defaults, and recipients with dark-mode
            email clients may see a different result. Test sends to real
            inboxes remain the only way to know exactly how a message will
            look on a given recipient's screen.
        </div>
    </div>
</body>
</html>
