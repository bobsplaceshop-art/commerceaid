<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import;

use RuntimeException;

/**
 * CSV reader for the data-import pipeline. Stage 016.
 *
 * One class, no dependencies beyond PHP's built-in
 * fgetcsv(). Reads a CSV file and returns its header row
 * and its rows as associative arrays.
 *
 * Rules:
 *
 *   - UTF-8 only. An invalid byte fails the whole read
 *     before any row is parsed. The error names the
 *     byte position and the line.
 *
 *   - A leading UTF-8 BOM is stripped from the first
 *     cell of the header row.
 *
 *   - Comma delimiter only. Semicolon and tab are
 *     future enhancements.
 *
 *   - fgetcsv() does the parse. The reader does not
 *     implement CSV parsing; quoted fields, embedded
 *     commas, embedded newlines, and escaped quotes are
 *     all fgetcsv's problem, not this class's.
 *
 *   - Duplicate header names are an error at read time
 *     (a silent last-wins would corrupt every row).
 *
 *   - A missing header name is an error.
 *
 *   - A row of all empty strings is skipped. That is how
 *     a trailing blank line is handled without asking
 *     the merchant to fix their file.
 *
 *   - The header is preserved verbatim. The column
 *     mapping UI maps the merchant's header names to the
 *     handler's expected fields. Normalisation happens
 *     there, not here.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class CsvReader
{
    private const BOM = "\xEF\xBB\xBF";

    /**
     * Read a CSV file.
     *
     * @return array{
     *     0: array<int, string>,
     *     1: array<int, array<string, string>>
     * }  [header, rows]
     *
     * @throws RuntimeException when the file cannot be
     *         read, is not valid UTF-8, has no header,
     *         or has duplicate header names.
     */
    public function read(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("CSV file not readable: {$path}");
        }

        $this->assertValidUtf8($path);

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("CSV file could not be opened: {$path}");
        }

        try {
            $header = $this->readHeader($handle, $path);
            $this->assertValidHeader($header, $path);

            $rows = [];
            while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                // fgetcsv returns [null] for a truly empty
                // line in PHP 8.1+, and [''] for a line
                // containing just a newline. Skip both.
                if ($line === [null] || $line === ['']) {
                    continue;
                }

                $row = $this->associate($header, $line);
                if ($row === null) {
                    continue;
                }
                $rows[] = $row;
            }

            return [$header, $rows];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Read the header row, stripping a UTF-8 BOM from the
     * first cell if present.
     *
     * @param resource $handle
     * @return array<int, string>
     */
    private function readHeader($handle, string $path): array
    {
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header === false || $header === [null]) {
            throw new RuntimeException(
                "CSV file has no header row: {$path}"
            );
        }

        if (isset($header[0])
            && str_starts_with($header[0], self::BOM)
        ) {
            $header[0] = substr($header[0], strlen(self::BOM));
        }

        return array_map(
            static fn($h): string => is_string($h) ? $h : '',
            $header
        );
    }

    /**
     * @param array<int, string> $header
     */
    private function assertValidHeader(array $header, string $path): void
    {
        $seen = [];
        foreach ($header as $index => $name) {
            if ($name === '') {
                throw new RuntimeException(
                    "CSV header column {$index} is empty: {$path}"
                );
            }
            if (isset($seen[$name])) {
                throw new RuntimeException(
                    "CSV header has duplicate column '{$name}': {$path}"
                );
            }
            $seen[$name] = true;
        }
    }

    /**
     * Associate one data row's values with the header names.
     * Returns null when the row is all empty (a spare blank
     * line in the file).
     *
     * Short rows (fewer cells than the header) are padded
     * with empty strings. Long rows (more cells than the
     * header) are truncated to the header length - the
     * extra cells belong to no known column and the
     * merchant's mapping does not know about them.
     *
     * @param array<int, string> $header
     * @param array<int, string|null> $line
     * @return array<string, string>|null
     */
    private function associate(array $header, array $line): ?array
    {
        $allEmpty = true;
        $row = [];
        foreach ($header as $i => $name) {
            $value = isset($line[$i]) ? $line[$i] : '';
            if ($value !== '') {
                $allEmpty = false;
            }
            $row[$name] = $value;
        }
        return $allEmpty ? null : $row;
    }

    /**
     * Fail the whole read on an invalid UTF-8 byte. fgetcsv()
     * would happily parse a Latin-1 file as garbage; the
     * importer would write corrupted data. Reject early.
     */
    private function assertValidUtf8(string $path): void
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("CSV file could not be read: {$path}");
        }
        if (!mb_check_encoding($contents, 'UTF-8')) {
            throw new RuntimeException(
                "CSV file is not valid UTF-8: {$path}. "
                . "Save the file as UTF-8 (without BOM) and retry."
            );
        }
    }
}
