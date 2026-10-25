<?php
/**
 * Standalone Customizer Layout.
 * No admin sidebar. No admin topbar. Just the builder.
 *
 * @var string $content
 * @var string $title
 */
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Page Builder') ?> | <?= e(config('app.name')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= versioned_asset('bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('fontawesome/css/all.min.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/main-v2.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/setup.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/app-v2.css') ?>" rel="stylesheet">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script>
        (function () {
            var stored = localStorage.getItem('ra-theme');
            var pref = stored || 'system';
            var resolved = pref === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : pref;
            document.documentElement.setAttribute('data-theme', resolved);
        })();
    </script>
</head>
<body>
    <?= $content ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= versioned_asset('js/app.js') ?>"></script>
    <script src="<?= versioned_asset('js/block-media-picker.js') ?>"></script>
</body>
</html>
