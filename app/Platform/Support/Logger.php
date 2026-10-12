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
        // Every log line carries the request ID when one has been
        // generated for the current request. The constant is
        // defined in public/index.php at the very top of the
        // request cycle, so it is available from the first log
        // call onward. On CLI the constant is absent and the
        // field is omitted.
        if (defined('CA_REQUEST_ID')) {
            $context['request_id'] = CA_REQUEST_ID;
        }

        $ts = date('Y-m-d H:i:s');
        $line = sprintf('[%s] %s: %s', $ts, strtoupper($level), $message);

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $line .= PHP_EOL;

        // Candidate-walk write path. Every candidate directory
        // is tried in priority order until one accepts the
        // write. If all fail, the entry falls through to the
        // SAPI's own log. There is no path through this method
        // that does not write the entry somewhere.
        //
        // The list comes from logDirs(). The primary candidate
        // is the project's storage/logs. The secondary is a
        // subdirectory of the system temp dir. On environments
        // where the project path is not writable by PHP (the
        // Windows/WSL bridge, a mounted drive without write
        // permissions, a container without a shared volume),
        // the temp-dir candidate keeps the log alive.
        //
        // is_writable() is deliberately not consulted. On the
        // Windows/WSL bridge it reads the file attribute bit,
        // not the ACL. It returns false on directories PHP can
        // actually write to. The write itself is the
        // authoritative test.

        // Detach the global error handler for the duration of
        // the write. App::registerErrorHandling() installs a
        // handler that converts every warning into an
        // ErrorException. Without this detach, a warning from
        // the fallback error_log() call would be re-thrown,
        // caught by renderException()'s try/catch, and
        // swallowed - the original exception never reaches the
        // log.
        //
        // The replacement handler returns true for every error
        // (silencing it). The previous handler is restored in
        // a finally block so nothing leaks.
        set_error_handler(static fn (): bool => true);
        try {
            foreach (self::logDirs() as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                if (!is_dir($dir)) {
                    continue;
                }

                $written = @file_put_contents(
                    $dir . '/app.log',
                    $line,
                    FILE_APPEND | LOCK_EX
                );
                if ($written !== false) {
                    return;
                }
            }

            // Every candidate failed. Fall through to the SAPI's
            // own log. Under Apache this is the Apache error_log;
            // under CLI it is stderr.
            @error_log('[ca-fallback] ' . rtrim($line));

            if (defined('STDERR')) {
                @fwrite(STDERR, '[ca-stderr] ' . $line);
            }
        } finally {
            restore_error_handler();
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

        // HTTP context. Every field is optional: on CLI the
        // REQUEST_METHOD and REQUEST_URI superglobals are
        // absent, and a log line from a CLI script omits them
        // cleanly. The presence of these fields is what makes
        // a support grep actionable:
        //
        //   request_method + request_uri  which endpoint failed
        //   user_agent                    which browser/client
        //   session_id                    which visitor session
        //   user_id                       which authenticated user
        //
        // A single reference ID lands on one line. A session ID
        // lands on every line for one visitor. A user ID lands
        // on every line for one account. All three greps are
        // possible after this commit; only the first was before.
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
        if ($method !== '') {
            $context['request_method'] = $method;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '') {
            $context['request_uri'] = $uri;
        }

        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($userAgent !== '') {
            $context['user_agent'] = $userAgent;
        }

        // session_status() returns PHP_SESSION_ACTIVE when a
        // session has been started. Reading session_id() when
        // no session is active returns an empty string, which
        // is filtered out below.
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sid = session_id();
            if ($sid !== '') {
                $context['session_id'] = $sid;
            }
        }

        // Read the user ID from the session. The framework's
        // Auth service writes it under this key. On an
        // unauthenticated request the key is absent and the
        // field is omitted.
        $uid = $_SESSION['user_id'] ?? null;
        if (is_int($uid) || is_string($uid)) {
            $context['user_id'] = $uid;
        }

        $message = $reference !== null
            ? "[{$reference}] " . $e->getMessage()
            : $e->getMessage();

        self::log('exception', $message, $context);
    }

    /**
     * Candidate directories for the log file, in priority order.
     *
     * The primary is the project's storage/logs. The fallback
     * is a subdirectory of the system temp dir, which is
     * writable by any process that can run PHP.
     *
     * The caller tries each in turn. is_writable() is not
     * consulted: on the Windows/WSL bridge it reads the file
     * attribute bit rather than the ACL, and returns false on
     * directories PHP can actually write to. The write itself
     * is the authoritative test.
     *
     * @return string[]
     */
    private static function logDirs(): array
    {
        $dirs = [];

        if (self::$basePath !== null) {
            $dirs[] = self::$basePath . '/storage/logs';
        }

        $tmp = sys_get_temp_dir();
        if ($tmp !== '' && is_dir($tmp)) {
            $dirs[] = rtrim($tmp, '/\\') . '/commerceaid-logs';
        }

        return $dirs;
    }
}
