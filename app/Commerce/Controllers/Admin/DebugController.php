<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Developer debug read endpoint. Pipeline commit 5/5.
 *
 * Returns the recent exception lines from storage/logs/app.log
 * so the browser dev overlay can display them. The overlay
 * already shows JavaScript errors; this endpoint feeds it the
 * server-side ones.
 *
 * The endpoint exists only when config('app.debug') is true.
 * In production it throws notFound(). The route is registered
 * in every environment, but the controller renders 404 in
 * any environment where the debug flag is off.
 *
 * Query parameters:
 *
 *   since   optional. A Unix timestamp in seconds. If given,
 *           only lines whose leading [YYYY-MM-DD HH:MM:SS]
 *           timestamp is strictly greater than this are
 *           returned. Defaults to 60 seconds ago.
 *
 *   limit   optional. Maximum number of lines to return.
 *           Defaults to 20, capped at 100.
 *
 * Filtering:
 *
 *   Only lines that carry a `request_id` field in their JSON
 *   context are returned. That field is written by
 *   Logger::log() for every line in an HTTP request and is
 *   absent from CLI lines. The result: the overlay sees the
 *   web request exceptions and nothing else.
 *
 * The response is JSON:
 *
 *   {"lines": ["<raw log line>", ...], "count": N}
 *
 * The raw line is returned as it appears in the file. The
 * client is expected to parse or display it. No server-side
 * JSON decode of the embedded context - the string is passed
 * through unchanged.
 *
 * Response headers:
 *   Cache-Control: no-store    never cached
 *   Content-Type: application/json
 */
final class DebugController extends AdminController
{
    /**
     * @param array<string,string> $params
     */
    public function recentErrors(Request $request, array $params = []): Response
    {
        // Guard. Debug endpoint only in debug mode.
        if (!config('app.debug', false)) {
            throw HttpException::notFound();
        }

        $since = (int) $request->query('since', time() - 60);
        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min(100, $limit));

        $logFile = $this->resolveLogFile();
        if ($logFile === null) {
            return $this->jsonResponse(['lines' => [], 'count' => 0]);
        }

        $lines = $this->tailMatching($logFile, $since, $limit);

        return $this->jsonResponse([
            'lines' => $lines,
            'count' => count($lines),
        ]);
    }

    /**
     * Resolve the app.log path. Tries the primary storage/logs
     * and the temp-dir fallback in the same order the logger
     * writes them. Returns null if neither exists.
     */
    private function resolveLogFile(): ?string
    {
        $base = defined('CA_BASE_PATH') ? CA_BASE_PATH : null;
        if ($base === null) {
            return null;
        }

        $primary = $base . '/storage/logs/app.log';
        if (is_file($primary)) {
            return $primary;
        }

        $tmp = sys_get_temp_dir();
        if ($tmp !== '') {
            $fallback = rtrim($tmp, '/\\') . '/commerceaid-logs/app.log';
            if (is_file($fallback)) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * Read the last N matching lines from the file. Iterates
     * from the end so a large log does not need to be read
     * entirely.
     *
     * @return string[]
     */
    private function tailMatching(string $file, int $since, int $limit): array
    {
        // Read the whole file for simplicity. Log files in
        // this project do not grow past a few MB between
        // rotations. If that changes, switch to a reverse
        // tail reader.
        $raw = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($raw === false) {
            return [];
        }

        $matched = [];
        for ($i = count($raw) - 1; $i >= 0 && count($matched) < $limit; $i--) {
            $line = $raw[$i];

            // Parse the leading timestamp: [YYYY-MM-DD HH:MM:SS]
            if (preg_match('/^\\[(\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2})\\]/', $line, $m) !== 1) {
                continue;
            }
            $ts = strtotime($m[1]);
            if ($ts === false || $ts <= $since) {
                continue;
            }

            // Require a request_id in the JSON tail. Lines
            // without it (CLI scripts, static test lines)
            // are ignored.
            if (!str_contains($line, '"request_id"')) {
                continue;
            }

            $matched[] = $line;
        }

        // The iteration walked backwards. Reverse so the
        // response is in chronological order.
        return array_reverse($matched);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function jsonResponse(array $data): Response
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $body = '{"lines":[],"count":0}';
        }

        return new Response($body, 200, [
            'Content-Type'  => 'application/json',
            'Cache-Control' => 'no-store',
        ]);
    }
}
