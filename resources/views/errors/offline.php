<?php
/**
 * Offline fallback page.
 *
 * Rendered by the service worker when a navigation request
 * fails and no cached page exists. The SW serves this from
 * cache, so this file must be self-contained: no dependency
 * on the storefront layout or any helper that requires a
 * fully booted request.
 *
 * The page uses the design tokens directly (the same palette
 * the storefront uses) so it feels native to the site. Theme
 * variables come from main-v2.css, which the service worker
 * precaches alongside this page. The pre-paint script reads
 * the same localStorage key as every other surface
 * ('ra-theme') so the theme applied here matches whatever the
 * customer chose elsewhere on the site.
 */
?>
<!doctype html>
<html lang="en" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline - <?= htmlspecialchars($brandName ?? 'Store', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="theme-color" content="#1a1a1a">
    <link href="/assets/css/main-v2.css" rel="stylesheet">
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
        * { box-sizing: border-box; }

        body {
            /* Radial accents layer on top of the theme's base
               background. --ra-bg is dark in dark theme and light
               in light theme; the two gradients stay subtle on
               either base because they are low-opacity
               translucencies. */
            background-color: var(--ra-bg, #f4f6fb);
            background-image: radial-gradient(1200px 600px at 80% -10%, rgba(245, 166, 35, .08), transparent 60%), radial-gradient(1000px 500px at 0% 110%, rgba(15, 76, 129, .10), transparent 60%);
        }

        .card {
            max-width: 480px;
            width: 100%;
            background: var(--ra-surface, #ffffff);
            border: 1px solid var(--ra-border, #e5e9f2);
            border-radius: 18px;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 24px 60px -20px rgba(15,23,42,.15);
        }

        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--ra-primary, #0f4c81), var(--ra-accent, #f5a623));
            color: #ffffff;
        }
        .icon svg { width: 32px; height: 32px; }

        h1 {
            color: var(--ra-text-strong, #020617);
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0 0 .5rem;
            letter-spacing: -.01em;
        }

        p {
            color: var(--ra-muted-2, #64748b);
            font-size: .95rem;
            line-height: 1.55;
            margin: 0 0 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .65rem 1.25rem;
            border-radius: 10px;
            background: var(--ra-primary, #0f4c81);
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            font-size: .92rem;
            transition: background-color .15s;
        }
        .btn:hover { background: var(--ra-primary-hover, #0d416e); }
    </style>
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;">
    <div class="card">
        <div class="icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                <path d="M5.5 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0V.5a.5.5 0 0 1 .5-.5zm3 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0V.5a.5.5 0 0 1 .5-.5zM0 8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v3.5a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5V8zm5 2a1 1 0 1 0-2 0 1 1 0 0 0 2 0zm5-1a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm3.5 2a.5.5 0 0 0 0 1h1a.5.5 0 0 0 0-1h-1z"/>
            </svg>
        </div>
        <h1>You're offline</h1>
        <p>
            The page you tried to open isn't available right now.
            Check your internet connection and try again.
        </p>
        <a href="/" class="btn" onclick="window.location.reload(); return false;">
            Try again
        </a>
    </div>
</body>
</html>
