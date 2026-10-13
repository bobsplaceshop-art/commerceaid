<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

/**
 * Reads and parses the application log file.
 *
 * The app log line format is:
 *
 *   [YYYY-MM-DD HH:MM:SS] LEVEL: message {json context}
 *
 * Where LEVEL is one of INFO, WARNING, ERROR, EXCEPTION.
 * The context is optional; CLI lines have none, and some
 * logger calls pass an empty context.
 *
 * The reader returns a list of parsed entries. Each entry
 * has the raw line preserved so a viewer can show it
 * verbatim, plus the parsed fields for filtering and
 * display.
 *
 * Two consumers exist:
 *
 *   DebugController::recentErrors
 *     The dev overlay's endpoint. Filters by time and
 *     request_id presence.
 *
 *   LogController::showSystem
 *     The admin System Logs screen. Filters by level, date
 *     range, and search term, paginated.
 *
 * Both call readAll() or readFiltered() and get the same
 * shape.
 *
 * @phpstan-type LogEntry array{
 *     raw:string,
 *     timestamp:?string,
 *     epoch:?int,
 *     level:?string,
 *     message:string,
 *     context:array<string,mixed>,
 *     repeat_count?:int
 * }
 */
final class LogReader
{
    /**
     * The list of log files to read, oldest first.
     *
     * The current app.log is always last. Dated archives
     * (app-YYYY-MM-DD.log) within the retention window come
     * before it, ordered oldest to newest.
     *
     * The primary location is storage/logs. If that does not
     * exist, the temp-dir fallback is used. Same candidate
     * order as the writer.
     *
     * @return string[]
     */
    public static function paths(int $days = 14): array
    {
        $base = defined('CA_BASE_PATH') ? CA_BASE_PATH : null;

        $dir = null;
        if ($base !== null) {
            $primary = $base . '/storage/logs';
            if (is_dir($primary)) {
                $dir = $primary;
            }
        }

        if ($dir === null) {
            $tmp = sys_get_temp_dir();
            if ($tmp !== '') {
                $fallback = rtrim($tmp, '/\\') . '/commerceaid-logs';
                if (is_dir($fallback)) {
                    $dir = $fallback;
                }
            }
        }

        if ($dir === null) {
            return [];
        }

        $paths = [];

        // Dated archives within the retention window, oldest first.
        $cutoff = time() - ($days * 86400);
        $archives = glob($dir . '/app-*.log') ?: [];
        // Sort by filename, which is date-ordered.
        sort($archives, SORT_STRING);
        foreach ($archives as $archive) {
            $mtime = @filemtime($archive);
            if ($mtime !== false && $mtime >= $cutoff) {
                $paths[] = $archive;
            }
        }

        // The current file is always read, even if it is empty.
        $current = $dir . '/app.log';
        if (is_file($current)) {
            $paths[] = $current;
        }

        return $paths;
    }

    /**
     * The current app log path, or null. Kept for callers that
     * only want the live file (the log viewer's "log path"
     * display).
     */
    public static function resolvePath(): ?string
    {
        $paths = self::paths();
        if ($paths === []) {
            return null;
        }
        // The current file is always last.
        return $paths[count($paths) - 1];
    }

    /**
     * Read every line from the log file, oldest first.
     *
     * @return array<int,LogEntry>
     */
    public static function readAll(?string $path = null, int $days = 14): array
    {
        // Single-file call: read just that file.
        if ($path !== null) {
            if (!is_file($path)) {
                return [];
            }
            return self::readFile($path);
        }

        // Multi-file: read every path in the window, oldest
        // first, and concatenate. This preserves chronological
        // order across the archive boundary.
        $entries = [];
        foreach (self::paths($days) as $file) {
            foreach (self::readFile($file) as $entry) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    /**
     * Read a single file into parsed entries.
     *
     * @return array<int,LogEntry>
     */
    private static function readFile(string $path): array
    {
        $raw = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($raw === false) {
            return [];
        }

        $out = [];
        foreach ($raw as $line) {
            $out[] = self::parseLine($line);
        }
        return $out;
    }

    /**
     * Read with filters and pagination. Applied in this order:
     *
     *   - level:       exact match against the parsed level
     *   - date_from:   timestamp >= date 00:00:00
     *   - date_to:     timestamp <= date 23:59:59
     *   - search:      substring match against message OR the
     *                  JSON-serialised context. A search for
     *                  "ERR-4a2f9c" matches lines that carry
     *                  that reference. A search for an email
     *                  matches context fields that contain it.
     *
     * Lines are returned newest first, then paginated.
     *
     * @param array{level?:string,date_from?:string,date_to?:string,search?:string} $filters
     * @return array{items:array<int,LogEntry>, total:int, page:int, per_page:int, pages:int}
     */
    public static function readFiltered(array $filters, int $page = 1, int $perPage = 50, ?string $path = null): array
    {
        $all = self::readAll($path);

        $level    = strtolower(trim((string) ($filters['level'] ?? '')));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo   = trim((string) ($filters['date_to'] ?? ''));
        $search   = strtolower(trim((string) ($filters['search'] ?? '')));

        $fromTs = ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom))
            ? strtotime($dateFrom . ' 00:00:00')
            : null;
        $toTs   = ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))
            ? strtotime($dateTo . ' 23:59:59')
            : null;

        $matched = [];
        foreach ($all as $entry) {
            if ($level !== '' && $level !== 'all') {
                if (($entry['level'] ?? '') !== strtolower($level)) {
                    continue;
                }
            }

            $ts = $entry['epoch'] ?? null;
            if ($fromTs !== null && ($ts === null || $ts < $fromTs)) {
                continue;
            }
            if ($toTs !== null && ($ts === null || $ts > $toTs)) {
                continue;
            }

            if ($search !== '') {
                $haystack = strtolower(
                    ($entry['message'] ?? '') . ' ' .
                    json_encode($entry['context'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                );
                if (!str_contains($haystack, $search)) {
                    continue;
                }
            }

            $matched[] = $entry;
        }

        // Newest first.
        $matched = array_reverse($matched);

        // Collapse consecutive identical raw lines within a
        // 60-second window. Three or more of the same line
        // becomes one entry with a repeat_count field. This
        // is what removes the favicon-404 spam: the same
        // message fires on every page load, and the merchant
        // does not need N copies of it.
        //
        // "Consecutive" means consecutive after the newest-
        // first sort: entries that are adjacent in time and
        // have identical raw bytes.
        $matched = self::collapseRepeats($matched);

        $total   = count($matched);
        $perPage = max(1, min(200, $perPage));
        $pages   = $total === 0 ? 0 : (int) ceil($total / $perPage);
        $page    = max(1, min($page, max(1, $pages)));

        $offset = ($page - 1) * $perPage;
        $items  = array_slice($matched, $offset, $perPage);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ];
    }

    /**
     * Collapse consecutive identical entries within a
     * 60-second window into a single entry carrying a
     * repeat_count. The first of the group is kept as the
     * representative; its raw bytes are unchanged.
     *
     * Entries with no timestamp are never collapsed
     * (nothing to compare).
     *
     * @param array<int,LogEntry> $entries
     * @return array<int,LogEntry>
     */
    private static function collapseRepeats(array $entries): array
    {
        /** @var array<int,LogEntry> $out */
        $out = [];
        $lastRaw = null;
        $lastEpoch = null;
        $lastIndex = null;

        foreach ($entries as $entry) {
            $raw = (string) ($entry['raw'] ?? '');
            $epoch = $entry['epoch'] ?? null;

            $canCollapse =
                $lastIndex !== null
                && $raw === $lastRaw
                && $epoch !== null
                && $lastEpoch !== null
                && abs((int) $epoch - (int) $lastEpoch) <= 60;

            if ($canCollapse) {
                // Copy the entry, mutate the copy, write the
                // copy back. PHPStan 2.x resets its view of
                // $out[$lastIndex] on a direct mutation and
                // widens the accumulator type. Assigning a
                // copy that started life as a LogEntry keeps
                // the shape intact.
                $entry = $out[$lastIndex];
                $count = (int) ($entry['repeat_count'] ?? 1);
                $entry['repeat_count'] = $count + 1;
                $out[$lastIndex] = $entry;
                $lastEpoch = $epoch;
                continue;
            }

            $out[] = $entry;
            $lastIndex = count($out) - 1;
            $lastRaw = $raw;
            $lastEpoch = $epoch;
        }

        return $out;
    }

    /**
     * Parse one raw log line into its parts. Never throws.
     *
     * @return LogEntry
     */
    public static function parseLine(string $line): array
    {
        $out = [
            'raw'       => $line,
            'timestamp' => null,
            'epoch'     => null,
            'level'     => null,
            'message'   => $line,
            'context'   => [],
        ];

        // [YYYY-MM-DD HH:MM:SS]
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*/', $line, $m) !== 1) {
            return $out;
        }
        $out['timestamp'] = $m[1];
        $ts = strtotime($m[1]);
        $out['epoch'] = $ts === false ? null : $ts;

        $rest = substr($line, strlen($m[0]));

        // LEVEL:
        if (preg_match('/^([A-Z]+):\s*(.*)$/s', $rest, $m2) === 1) {
            $out['level']   = strtolower($m2[1]);
            $out['message'] = $m2[2];
        }

        // Trailing JSON object.
        $open = strpos($out['message'], '{');
        if ($open !== false) {
            $contextJson = substr($out['message'], $open);
            $decoded = json_decode($contextJson, true);
            if (is_array($decoded)) {
                $out['context'] = $decoded;
                // Trim the context off the message for a
                // cleaner display.
                $out['message'] = rtrim(substr($out['message'], 0, $open));
            }
        }

        return $out;
    }
}
