# Git Hooks

This directory holds git hooks for the project. Hooks here are
managed via `core.hooksPath` (set by the installer) rather than
dropped into `.git/hooks/` directly, so they are version-controlled
and apply to every clone.

## Installation

After cloning the repository, run once:

    composer hook:install

Or directly:

    php bin/install-hooks.php

This writes the actual hook files into this directory using
machine-specific absolute paths (the path to the PHP binary that
ran the installer). That means the hook files themselves are
generated per-machine and are not committed. Only this README,
`bin/install-hooks.php`, and any non-generated hook scripts are
tracked.

## Available Hooks

### pre-commit

Runs `php bin/lint.php` (PHP syntax check across `app/` and
`routes/`). Blocks the commit if any file fails to parse.

Bypass with:

    git commit --no-verify

Use the bypass carefully. The linter is fast and catches real
problems; the only good reason to skip it is a WIP commit you
know is broken.

## Regenerating

If the PHP binary moves (Laragon upgrade, PATH change), re-run:

    composer hook:install

The installer overwrites `pre-commit` with the current PHP path.
