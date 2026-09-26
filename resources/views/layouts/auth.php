<?php
/**
 * Auth layout - used by login, forgot-password, reset-password.
 *
 * Brighter palette - soft off-white background, white card, vivid
 * accents. Branding is pulled from config('branding') where available.
 *
 * Theme: on the login page there is no authenticated user yet, so
 * we cannot read the theme from the database. We use localStorage
 * (set by the app layout on the last visit) as a best-effort hint.
 * Default fallback is 'system'.
 */
$branding = function_exists('config') ? (array) config('branding', []) : [];
$brandName = $branding['product_name'] ?? 'Anaya Global';
$tagline   = $branding['tagline']      ?? 'Global Commerce Platform';
$primary   = $branding['primary_color']   ?? '#2563eb';
$accent    = $branding['accent_color']    ?? '#60a5fa';

$errors  = $errors  ?? [];
$success = $success ?? null;
$formError = is_array($errors) ? ($errors['_form'] ?? null) : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Sign in') ?> - <?= e($brandName) ?></title>

    <?php /* Pre-paint theme resolution - runs before stylesheets apply */ ?>
    <script>
    (function () {
        var theme = 'system';
        try {
            var stored = localStorage.getItem('ra-theme');
            if (stored === 'dark' || stored === 'light' || stored === 'system') {
                theme = stored;
            }
        } catch (e) {}
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <style>
        :root {
            --brand-primary: <?= e($primary) ?>;
            --brand-accent:  <?= e($accent) ?>;
        }

        body {
            background:
                radial-gradient(1200px 600px at 80% -10%, rgba(96,165,250,.15), transparent 60%),
                radial-gradient(1000px 500px at 0% 110%,  rgba(37,99,235,.12),  transparent 60%);
            background-color: var(--ra-bg, #f4f6fb);
            color: var(--ra-fg, #0f172a);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: var(--ra-card-bg, #ffffff);
            border: 1px solid var(--ra-border, #e5e9f2);
            border-radius: 18px;
            padding: 2.5rem 2.25rem 2.25rem;
            box-shadow:
                0 1px 2px rgba(15,23,42,.04),
                0 24px 60px -20px rgba(15,23,42,.15);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: .65rem;
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--ra-fg, #0f172a);
            letter-spacing: -.01em;
        }

        .brand-mark {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-accent));
            box-shadow: 0 6px 16px -6px rgba(37,99,235,.55);
        }

        .muted {
            color: var(--ra-muted, #64748b);
        }

        .form-label {
            font-weight: 500;
            color: var(--ra-fg, #334155);
            font-size: .92rem;
            margin-bottom: .4rem;
        }

        .form-control {
            background: var(--ra-input-bg, #ffffff);
            border: 1px solid var(--ra-border, #d1d9e6);
            color: var(--ra-fg, #0f172a);
            border-radius: 10px;
            padding: .65rem .85rem;
            font-size: .95rem;
            transition: border-color .15s, box-shadow .15s;
        }

        .form-control:focus {
            background: var(--ra-input-bg, #ffffff);
            border-color: var(--brand-primary);
            color: var(--ra-fg, #0f172a);
            box-shadow: 0 0 0 4px rgba(37,99,235,.12);
        }

        .form-control::placeholder {
            color: var(--ra-muted, #94a3b8);
        }

        .btn-primary {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
            border-radius: 10px;
            font-weight: 600;
            padding: .7rem 1rem;
            transition: background-color .15s, transform .05s;
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }

        .btn-primary:active {
            transform: translateY(1px);
        }

        .form-check-label {
            color: var(--ra-muted, #475569);
        }

        .pw-toggle {
            color: var(--ra-muted, #94a3b8);
        }

        .pw-toggle:hover {
            color: var(--ra-fg, #475569);
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: .75rem 1rem;
            font-size: .9rem;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: .75rem 1rem;
            font-size: .9rem;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand mb-1">
            <span class="brand-mark"></span>
            <span><?= e($brandName) ?></span>
        </div>
        <p class="muted mb-4"><?= e($tagline) ?></p>

        <?php if ($formError !== null): ?>
            <div class="alert alert-error mb-3" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?= e((string) $formError) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success mb-3" role="alert">
                <i class="bi bi-check-circle me-1"></i>
                <?= e((string) $success) ?>
            </div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
