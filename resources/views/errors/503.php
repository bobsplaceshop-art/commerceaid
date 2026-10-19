<?php
$title = $title ?? 'Scheduled Maintenance';
$storeName = $store->name ?? 'Our Store';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> - <?= e($storeName) ?></title>
    
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
            opacity: 0.5;
            animation: float 10s infinite ease-in-out alternate;
        }
        .sf-bg-orb-1 {
            width: 400px; height: 400px;
            background: #e5e2db;
            top: -100px; left: -100px;
        }
        .sf-bg-orb-2 {
            width: 350px; height: 350px;
            background: var(--sf-charcoal, #2b2b2b);
            bottom: -100px; right: -50px;
            animation-delay: -5s;
            opacity: 0.1;
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 50px) scale(1.1); }
        }
        
        .sf-maintenance-box {
            max-width: 600px;
            width: 100%;
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

        .sf-icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: var(--sf-charcoal, #2b2b2b);
            border-radius: 50%;
            margin-bottom: 2rem;
            color: var(--sf-gold, #d4af37);
            font-size: 2.5rem;
        }

        h1 {
            font-family: var(--sf-font-display, Georgia, serif);
            font-size: clamp(2rem, 5vw, 2.5rem);
            line-height: 1.2; margin-bottom: 1rem; color: var(--sf-charcoal, #2b2b2b);
        }

        p {
            font-size: 1.15rem;
            line-height: 1.6;
            color: var(--sf-muted, #666);
            margin-bottom: 2rem;
        }

        .sf-footer {
            font-size: 0.85rem; color: var(--sf-muted, #666);
            margin-top: 3rem; padding-top: 2rem; border-top: 1px solid rgba(0,0,0,0.05);
        }
        .sf-footer a { color: var(--sf-charcoal, #2b2b2b); font-weight: 600; text-decoration: none; }
        .sf-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="sf-bg-orb sf-bg-orb-1"></div>
    <div class="sf-bg-orb sf-bg-orb-2"></div>

    <div class="sf-maintenance-box">
        <div class="sf-icon-wrapper">⏳</div>
        
        <h1>We'll be right back.</h1>
        
        <p>
            <?= e($message) ?>
        </p>

        <div class="sf-footer">
            Are you the store owner? <a href="/login">Log in here</a>.<br>
            Need help? Contact <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>
        </div>
    </div>
</body>
</html>
