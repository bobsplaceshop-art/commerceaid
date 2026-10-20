<?php
$title = $title ?? 'Coming Soon';
$storeName = $store->name ?? 'Our New Store';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($storeName) ?> - Coming Soon</title>
    
    <link rel="stylesheet" href="<?= versioned_asset('/assets/css/storefront.css') ?>">
    
    <style>
        body {
            background-color: var(--sf-cream, #fcfbf9);
            color: var(--sf-charcoal, #2b2b2b);
            font-family: var(--sf-font-body, system-ui, sans-serif);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 2rem;
            text-align: center;
            overflow: hidden;
            position: relative;
        }

        /* Fluid animated background */
        .sf-bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.6;
            animation: float 10s infinite ease-in-out alternate;
        }
        .sf-bg-orb-1 {
            width: 400px; height: 400px;
            background: var(--sf-gold, #d4af37);
            top: -100px; left: -100px;
        }
        .sf-bg-orb-2 {
            width: 350px; height: 350px;
            background: #e5e2db;
            bottom: -100px; right: -50px;
            animation-delay: -5s;
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 50px) scale(1.1); }
        }
        
        .sf-coming-soon {
            max-width: 650px;
            width: 100%;
            /* semi-transparent */
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 4rem 3rem;
            border-radius: 16px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.08);
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255,255,255,0.5);
        }

        .sf-coming-soon::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--sf-gold, #d4af37), var(--sf-charcoal, #2b2b2b));
            border-radius: 16px 16px 0 0;
        }

        .sf-cs-logo { max-width: 200px; max-height: 80px; margin: 0 auto 2rem; display: block; }

        .sf-cs-badge {
            display: inline-block;
            background: var(--sf-gold, #d4af37);
            color: var(--sf-charcoal, #2b2b2b);
            font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.1em;
            padding: 0.4rem 1rem; border-radius: 50px; margin-bottom: 1.5rem;
        }

        h1 {
            font-family: var(--sf-font-display, Georgia, serif);
            font-size: clamp(2rem, 5vw, 3rem);
            line-height: 1.1; margin-bottom: 1rem; color: var(--sf-charcoal, #2b2b2b);
        }

        p.sf-cs-message { font-size: 1.15rem; line-height: 1.6; color: var(--sf-muted, #666); margin-bottom: 2.5rem; }

        .sf-cs-subscribe { max-width: 450px; margin: 0 auto 2rem; text-align: left; }
        .sf-cs-subscribe label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--sf-charcoal, #2b2b2b); }
        .sf-cs-input-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .sf-cs-input-group input {
            flex: 1; min-width: 200px; padding: 0.8rem 1rem;
            border: 1px solid var(--sf-border, #ddd); border-radius: 6px;
            font-size: 1rem; font-family: inherit;
        }
        .sf-cs-input-group input:focus { outline: none; border-color: var(--sf-gold, #d4af37); box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1); }
        .sf-cs-input-group button {
            padding: 0.8rem 1.5rem; background: var(--sf-charcoal, #2b2b2b);
            color: #fff; border: none; border-radius: 6px; font-size: 1rem;
            font-weight: 600; cursor: pointer; transition: background 0.2s; font-family: inherit;
        }
        .sf-cs-input-group button:hover { background: var(--sf-gold, #d4af37); color: var(--sf-charcoal, #2b2b2b); }

        .sf-cs-footer {
            font-size: 0.85rem; color: var(--sf-muted, #666);
            margin-top: 3rem; padding-top: 2rem; border-top: 1px solid rgba(0,0,0,0.05);
        }
        .sf-cs-footer a { color: var(--sf-charcoal, #2b2b2b); font-weight: 600; text-decoration: none; }
        .sf-cs-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="sf-bg-orb sf-bg-orb-1"></div>
    <div class="sf-bg-orb sf-bg-orb-2"></div>

    <div class="sf-coming-soon">
        <div class="sf-cs-badge">Launching Soon</div>

        <?php if (!empty($store->logo_file_id)): ?>
            <img src="<?= e(store_logo_url()) ?>" alt="<?= e($storeName) ?>" class="sf-cs-logo">
        <?php else: ?>
            <h2 style="font-family: var(--sf-font-display, serif); font-size: 2rem; margin-bottom: 2rem;"><?= e($storeName) ?></h2>
        <?php endif; ?>

        <h1>Something big is coming.</h1>
        
        <p class="sf-cs-message">
            <?= e($message) ?>
        </p>

        <div class="sf-cs-subscribe">
            <form action="/newsletter/subscribe" method="POST">
                <?= csrf_field() ?>
                <label>Be the first to know when we launch.</label>
                <div class="sf-cs-input-group">
                    <input type="email" name="email" placeholder="Enter your email address" required>
                    <button type="submit">Notify Me</button>
                </div>
            </form>
        </div>

        <div class="sf-cs-footer">
            Are you the store owner? <a href="/login">Log in here</a>.<br>
            Need help? Contact <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>
        </div>
    </div>
</body>
</html>
