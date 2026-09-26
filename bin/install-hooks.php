#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/install-hooks.php
 *
 * Installs git hooks for this repository.
 *
 * Writes a portable .githooks/pre-commit that finds the correct PHP
 * binary at runtime, no matter which environment (Laragon, WSL, CI)
 * invokes the commit. Also sets `git config core.hooksPath .githooks`.
 *
 * The hook generation is deterministic. There is no machine-specific
 * content in the file. Regenerating it on another machine produces
 * the same bytes.
 *
 * Usage:
 *   php bin/install-hooks.php
 *   composer hook:install
 */

$root = dirname(__DIR__);
$hooksDir = $root . DIRECTORY_SEPARATOR . '.githooks';

if (!is_dir($hooksDir)) {
    if (!mkdir($hooksDir, 0755, true) && !is_dir($hooksDir)) {
        fwrite(STDERR, "ERROR: could not create .githooks/\n");
        exit(1);
    }
}

/**
 * Portable pre-commit hook.
 *
 * Resolution order for the PHP binary:
 *   1. `php` on PATH (works in WSL, macOS, Linux, CI)
 *   2. Laragon's absolute path (works in Windows cmd/git-bash when
 *      php is not on PATH)
 *
 * If neither is found, the hook exits 1 and blocks the commit.
 */
$hook = <<<'SH'
#!/bin/sh
# =====================================================================
# pre-commit hook
# =====================================================================
# Portable. Works in WSL, Linux, macOS, CI, and Git for Windows.
# Resolves the PHP binary at runtime instead of baking in a path.
#
# Regenerate with: composer hook:install
# Bypass with:     git commit --no-verify
# =====================================================================

status=0

if command -v php >/dev/null 2>&1; then
    # PHP on PATH. Use it.
    php bin/lint.php
    status=$?
elif [ -x "/c/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe" ]; then
    # Laragon fallback for Windows environments where php is not
    # on PATH but Laragon is installed at the standard location.
    "/c/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe" bin/lint.php
    status=$?
else
    echo "pre-commit: cannot find a PHP binary." >&2
    echo "Install PHP, or edit .githooks/pre-commit to point at your PHP." >&2
    exit 1
fi

if [ $status -ne 0 ]; then
    echo ""
    echo "Commit blocked by pre-commit hook."
    echo "Fix the syntax errors above and try again."
    echo ""
    echo "To bypass (use with care): git commit --no-verify"
    exit 1
fi

exit 0
SH;

$hookPath = $hooksDir . DIRECTORY_SEPARATOR . 'pre-commit';
if (file_put_contents($hookPath, $hook) === false) {
    fwrite(STDERR, "ERROR: could not write {$hookPath}\n");
    exit(1);
}

@chmod($hookPath, 0755);

// Point git at the hooks directory.
$out = [];
$code = 0;
exec('git config core.hooksPath .githooks', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "ERROR: could not set git core.hooksPath\n");
    exit(1);
}

echo "Hooks installed.\n";
echo "  Hook file:        .githooks/pre-commit\n";
echo "  core.hooksPath:   .githooks\n";
echo "\n";
echo "The pre-commit hook will run `php bin/lint.php` before every\n";
echo "commit. It resolves the PHP binary at runtime, so it works in\n";
echo "Laragon, WSL, and CI without any machine-specific state.\n";
echo "\n";
echo "Bypass with: git commit --no-verify\n";
exit(0);
