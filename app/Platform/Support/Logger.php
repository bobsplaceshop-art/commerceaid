<?php

declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use Throwable;

/**
 * Simple file logger. Writes to storage/logs/.
 * No external dependencies.
 *
 * Exceptions may be logged with a short reference ID (e.g. "ERR-4a2f9c")
 * that is also shown to the user on the error page. Support can grep
 * the log for that ID to find the exact stack trace without needing the
 * user to describe what they were doing.
 */
final class Logger
{
    private static ?string $basePath = null;

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/\\');
    }

    /** @param array<string,mixed> $context */
    public static function log(string $level, string $message, array $context = []): void
    {
        $ts = date('Y-m-d H:i:s');
        $line = sprintf('[%s] %s: %s', $ts, strtoupper($level), $message);

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $line .= PHP_EOL;

        // Three-tier write path. Each tier is tried only if the
        // previous one failed. The method returns as soon as any
        // tier writes successfully. There is no path through this
        // method that does not write the entry to somewhere.
        //
        // Tier 1: the structured app log.
        // Tier 2: error_log(), the SAPI's configured log target.
        //         For mod_php under Apache this is the Apache
        //         error_log. For CLI it is stderr.
        // Tier 3: STDERR directly, when the constant is defined.
        //         Belt and braces for CLI contexts where
        //         error_log() may route somewhere unreachable.
        //
        // The [ca-fallback] and [ca-stderr] prefixes make the
        // tiers greppable in whichever log actually received
        // them.

        $dir = self::logDir();
        if ($dir !== null) {
            $written = @file_put_contents(
                $dir . '/app.log',
                $line,
                FILE_APPEND | LOCK_EX
            );
            if ($written !== false) {
                return;
            }
        }

        error_log('[ca-fallback] ' . rtrim($line));

        if (defined('STDERR')) {
            @fwrite(STDERR, '[ca-stderr] ' . $line);
        }
    }

    /** @param array<string,mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    /**
     * Log an exception. If a $reference is provided, it is prepended to
     * the message and included in the context so both `grep <ref>` and
     * structured log tooling can find it.
     */
    public static function exception(Throwable $e, ?string $reference = null): void
    {
        $context = [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        if ($reference !== null) {
            $context['ref'] = $reference;
        }

        $message = $reference !== null
            ? "[{$reference}] " . $e->getMessage()
            : $e->getMessage();

        self::log('exception', $message, $context);
    }

    private static function logDir(): ?string
    {
        if (self::$basePath === null) {
            return null;
        }
        $dir = self::$basePath . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // After mkdir the directory may still not exist if the
        // attempt failed. Check both conditions before returning
        // the path; the caller falls through to error_log() and
        // STDERR when this returns null.
        return (is_dir($dir) && is_writable($dir)) ? $dir : null;
    }
}
