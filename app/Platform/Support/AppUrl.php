<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

/**
 * Absolute URL builder.
 *
 * For emails and any other context where a path-only link will not
 * do, an absolute URL needs a scheme and a host. This class resolves
 * them from the current request when available, falling back to
 * config('app.url') otherwise.
 *
 * Host resolution order:
 *   1. $_SERVER['HTTP_HOST'] - the host the request actually
 *      arrived on. Correct for any deployment the request came
 *      from, including subdomain or port variations.
 *   2. config('app.url') - the configured base URL. Used when
 *      HTTP_HOST is absent (CLI scripts, tests, misconfigured
 *      requests).
 *   3. 'localhost' - last-resort fallback. Produces a usable
 *      URL even when nothing else is configured.
 *
 * Scheme resolution: HTTPS is on, or X-Forwarded-Proto is https,
 * produces an https:// URL. Everything else is http://.
 *
 * This class exists because the same absolute-URL logic was
 * duplicated in Commerce\AuthController and
 * Commerce\Admin\AdminUserController, with the same hardcoded
 * 'localhost:8084' fallback that only ever matched one dev
 * environment. One source of truth prevents that drift.
 *
 * No state. All methods static.
 */
final class AppUrl
{
    public static function absolute(string $path): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

        if ($host === '') {
            $configured = (string) config('app.url', '');
            if ($configured !== '') {
                $parsed = parse_url($configured, PHP_URL_HOST);
                if (is_string($parsed) && $parsed !== '') {
                    $host = $parsed;
                    // Preserve a port from the configured URL when
                    // present, so http://localhost:8084 round-trips
                    // to http://localhost:8084/... rather than
                    // http://localhost/...
                    $port = parse_url($configured, PHP_URL_PORT);
                    if (is_int($port)) {
                        $host .= ':' . $port;
                    }
                }
            }
        }

        if ($host === '') {
            $host = 'localhost';
        }

        $https = ($_SERVER['HTTPS'] ?? '') === 'on'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        $scheme = $https ? 'https' : 'http';

        return $scheme . '://' . $host . $path;
    }
}
