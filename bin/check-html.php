#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/check-html.php - HTML structural integrity check.
 *
 * Scans every .php file under resources/views/ and validates
 * four structural rules that catch the bug classes this project
 * has hit more than once:
 *
 *   1. Nested <form> elements. HTML disallows form-in-form.
 *      The browser silently discards the first inner <form>
 *      and closes the outer form on its </form>. Buttons that
 *      target the discarded form call .submit() on null.
 *      See docs/REMEMBER.md Pattern 3 and Pattern 27.
 *
 *   2. A modal partial included from inside an open <form>
 *      region. Every modal partial carries its own <form>
 *      (or, in one case, three). Rendering it as a child
 *      of a page form triggers rule 1 on the rendered output.
 *      The include is the bug; the partial is correct.
 *
 *   3. Unescaped JSON in a data-* attribute. A double-quoted
 *      attribute whose PHP echo contains json_encode() output
 *      collides on the JSON's own double quotes and truncates
 *      the attribute. See docs/REMEMBER.md Pattern 13.
 *
 *   4. Unbalanced data-* attribute quotes. A data-* attribute
 *      whose opening quote has no matching close on the same
 *      logical line truncates the tag.
 *
 * The checker does not modify files. It reports. Fixes are
 * applied by hand in a separate patch.
 *
 * Exit codes:
 *   0 - no hard errors (warnings may be present)
 *   1 - at least one hard error
 *
 * Usage:
 *   php bin/check-html.php
 *   composer html
 */

$root = dirname(__DIR__);
$viewsDir = $root . '/resources/views';

$errors   = [];
$warnings = [];
$checked  = 0;

// -----------------------------------------------------------------
// Modal partials known to contain their own <form>
// -----------------------------------------------------------------
//
// Maintained as an explicit list. When a new modal partial is
// added, add it here. When a modal partial stops containing a
// form, remove it. The list is the source of truth for rule 3.
//
// Read A (this session) produced this list. 24 of 26 modal
// partials contain at least one <form>. The two that do not
// are _history-modal.php (display only) and
// _image-picker-modal.php (a control strip whose submission
// is carried by the parent form).

$modalPartialsWithForm = [
    'admin/attributes/_delete-modal',
    'admin/brands/_delete-modal',
    'admin/categories/_delete-modal',
    'admin/coupons/_delete-modal',
    'admin/customers/_address-delete-modal',
    'admin/customers/_group-modal',
    'admin/customers/_status-modal',
    'admin/customer_approvals/_reject-modal',
    'admin/customer_approvals/_reset-modal',
    'admin/forms/_field-modal',
    'admin/inventory/_adjust-modal',
    'admin/media/_delete-modal',
    'admin/media/_detail-modal',
    'admin/orders/_status-modal',
    'admin/orders/_note-modal',
    'admin/products/_delete-modal',
    'admin/returns/_reject-modal',
    'admin/returns/_receive-modal',
    'admin/returns/_refund-modal',
    'admin/reviews/_delete-modal',
    'admin/shipping/_delete-modal',
    'admin/tax/_class-modal',
    'admin/tax/_delete-modal',
];

// -----------------------------------------------------------------
// Parse helpers
// -----------------------------------------------------------------

/**
 * Return a list of <form> open/close positions for a file body.
 * Each entry: ['type' => 'open'|'close', 'offset' => int, 'line' => int].
 *
 * Does not attempt to be a full HTML parser. It ignores anything
 * inside <!-- ... --> comments and inside <script> ... </script>
 * blocks. Those are the two places a literal "<form" string can
 * appear without being a form element.
 *
 * @return array<int, array{type:string, offset:int, line:int}>
 */
function strip_php_regions(string $src): string
{
    $len = strlen($src);
    $shadow = $src;
    $openTags = ['<?php', '<?='];
    $i = 0;
    while (true) {
        $earliest = false;
        foreach ($openTags as $tag) {
            $pos = stripos($shadow, $tag, $i);
            if ($pos !== false && ($earliest === false || $pos < $earliest)) {
                $earliest = $pos;
            }
        }
        if ($earliest === false) break;
        $close = strpos($shadow, '?>', $earliest);
        if ($close === false) {
            $length = $len - $earliest;
            $shadow = substr_replace($shadow, str_repeat(' ', $length), $earliest, $length);
            break;
        }
        $length = $close - $earliest + 2;
        $shadow = substr_replace($shadow, str_repeat(' ', $length), $earliest, $length);
        $i = $close + 2;
    }
    return $shadow;
}

function form_events(string $src): array
{
    $events = [];
    $len = strlen($src);

    // Build a "shadow" copy of the source where PHP regions,
    // HTML comments, and script contents are replaced with
    // spaces. Offsets and line counts are preserved because
    // the length does not change.
    $shadow = strip_php_regions($src);

    // Strip <!-- ... --> comments.
    $i = 0;
    while (true) {
        $open = strpos($shadow, '<!--', $i);
        if ($open === false) break;
        $close = strpos($shadow, '-->', $open);
        if ($close === false) break;
        $length = $close - $open + 3;
        $shadow = substr_replace($shadow, str_repeat(' ', $length), $open, $length);
        $i = $close + 3;
    }

    // Strip <script ...> ... </script> blocks.
    $i = 0;
    while (true) {
        $open = stripos($shadow, '<script', $i);
        if ($open === false) break;
        $gt = strpos($shadow, '>', $open);
        if ($gt === false) break;
        $close = stripos($shadow, '</script>', $gt);
        if ($close === false) break;
        $length = $close - $open + 9;
        $shadow = substr_replace($shadow, str_repeat(' ', $length), $open, $length);
        $i = $close + 9;
    }

    // Now walk the shadow and find <form ...> opens and </form>
    // closes.
    $i = 0;
    while (true) {
        $open = stripos($shadow, '<form', $i);
        $close = stripos($shadow, '</form>', $i);

        if ($open === false && $close === false) break;

        if ($open !== false && ($close === false || $open < $close)) {
            // Confirm it is a real open tag: followed by whitespace,
            // '>', or '/' (self-closing). Not `<formula`.
            $after = $open + 5;
            $ok = $after >= $len
                || in_array($shadow[$after], ["\t", "\n", "\r", ' ', '>', '/'], true);
            if ($ok) {
                $events[] = [
                    'type'   => 'open',
                    'offset' => $open,
                    'line'   => substr_count(substr($shadow, 0, $open), "\n") + 1,
                ];
                $i = $open + 5;
                continue;
            }
            $i = $open + 5;
            continue;
        }

        $events[] = [
            'type'   => 'close',
            'offset' => $close,
            'line'   => substr_count(substr($shadow, 0, $close), "\n") + 1,
        ];
        $i = $close + 7;
    }

    usort($events, fn ($a, $b) => $a['offset'] <=> $b['offset']);
    return $events;
}

/**
 * Return a list of view_partial(...) calls for a file body.
 * Each entry: ['name' => string, 'offset' => int, 'line' => int].
 *
 * The regex is deliberately conservative. It matches:
 *   view_partial('dotted.name')
 *   view_partial("dotted.name")
 *   view_partial('dotted.name', [...])
 * The name is captured without the leading/trailing quotes.
 *
 * @return array<int, array{name:string, offset:int, line:int}>
 */
function view_partial_calls(string $src): array
{
    $calls = [];
    $re = "/view_partial\s*\(\s*(['\"])([A-Za-z0-9_.\\/-]+)\\1/";
    if (preg_match_all($re, $src, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $i => $m) {
            $offset = $m[1];
            $name   = $matches[2][$i][0];
            $calls[] = [
                'name'   => $name,
                'offset' => $offset,
                'line'   => substr_count(substr($src, 0, $offset), "\n") + 1,
            ];
        }
    }
    return $calls;
}

/**
 * Given a list of form events and a byte offset, return the
 * depth of open forms at that offset. Depth 0 means the offset
 * is outside every form. Depth > 0 means it is inside.
 */
function form_depth_at(array $events, int $offset): int
{
    $depth = 0;
    foreach ($events as $ev) {
        if ($ev['offset'] >= $offset) break;
        if ($ev['type'] === 'open') $depth++;
        elseif ($ev['type'] === 'close') $depth = max(0, $depth - 1);
    }
    return $depth;
}

// -----------------------------------------------------------------
// Walk views, apply every rule
// -----------------------------------------------------------------

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $viewsDir,
        RecursiveDirectoryIterator::SKIP_DOTS
    )
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    if (strtolower($file->getExtension()) !== 'php') continue;

    $path = $file->getPathname();
    $src  = file_get_contents($path);
    if ($src === false) continue;
    $checked++;

    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $rel = str_replace('\\', '/', $rel);

    // -----------------------------------------------------------------
    // Rule 1 - nested <form>
    // -----------------------------------------------------------------
    $events = form_events($src);
    $depth = 0;
    $firstOpenLine = 0;
    foreach ($events as $ev) {
        if ($ev['type'] === 'open') {
            if ($depth === 0) {
                $firstOpenLine = $ev['line'];
            }
            $depth++;
            if ($depth >= 2) {
                $errors[] = [
                    'file'    => $rel,
                    'line'    => $ev['line'],
                    'message' => "Nested <form> opened at depth {$depth} "
                               . "(outer opened at line {$firstOpenLine}). "
                               . "HTML discards the first inner form.",
                ];
            }
        } elseif ($ev['type'] === 'close') {
            $depth = max(0, $depth - 1);
        }
    }

    // -----------------------------------------------------------------
    // Rule 2 and Rule 3 - modal includes inside a form region
    // -----------------------------------------------------------------
    $calls = view_partial_calls($src);
    foreach ($calls as $call) {
        $isModal = str_contains($call['name'], 'modal')
                || str_contains($call['name'], '_modal');

        if (!$isModal) continue;

        $depth = form_depth_at($events, $call['offset']);
        if ($depth === 0) continue;

        // Inside a form region. Is the modal one of the known
        // form-bearing partials?
        $normalizedName = str_replace('.', '/', $call['name']);
        $carriesForm = in_array($normalizedName, $modalPartialsWithForm, true);

        if ($carriesForm) {
            $errors[] = [
                'file'    => $rel,
                'line'    => $call['line'],
                'message' => "Modal partial '{$call['name']}' contains its own "
                           . "<form> and is included inside a parent form "
                           . "(depth {$depth}). The browser will discard the "
                           . "modal's form. Move the include outside the "
                           . "parent </form>.",
            ];
        } else {
            // Modal without an internal form, but included inside a
            // form region. Lower severity, but still worth flagging:
            // a future edit to the partial that adds a <form> would
            // trigger rule 1 silently.
            $warnings[] = [
                'file'    => $rel,
                'line'    => $call['line'],
                'message' => "Modal partial '{$call['name']}' is included "
                           . "inside a parent form (depth {$depth}) but "
                           . "does not currently contain a <form>. Any "
                           . "future <form> added to the partial will "
                           . "trigger nested-form failure.",
            ];
        }
    }

    // -----------------------------------------------------------------
    // Rule 4 - unescaped JSON in a data-* attribute
    // -----------------------------------------------------------------
    //
    // Match a data-* attribute whose value is a PHP echo of
    // json_encode(...). If the echo is not wrapped in e(...),
    // the JSON's own double quotes collide with the attribute
    // delimiter.
    //
    // The bug pattern, in prose (the literal PHP short-echo
    // delimiters are omitted here on purpose: writing them
    // inside a // comment would close the PHP block, because
    // PHP honours the close-tag sequence even inside a
    // single-line comment, and everything after it on the
    // file is then treated as HTML output):
    //
    //   BAD  - attribute value is an unescaped short-echo of
    //          json_encode(...)
    //   BAD  - attribute value is a short-echo of json_encode
    //          wrapped in single quotes but with no escaping
    //   OK   - attribute value is a short-echo of e(json_encode(...))
    //   OK   - attribute value is a short-echo of json_encode
    //          inside a single-quoted attribute, with e() around it
    //
    // The check: find every data-*="..." or data-*='...' where
    // the body contains json_encode( but does not contain e(
    // immediately wrapping it.
    $reAttr = '/data-[a-z0-9-]+\s*=\s*(["\'])(.*?)\1/s';
    if (preg_match_all($reAttr, $src, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $i => $m) {
            $wholeAttr = $m[0];
            $offset    = $m[1];
            $line      = substr_count(substr($src, 0, $offset), "\n") + 1;

            if (!str_contains($wholeAttr, 'json_encode')) {
                continue;
            }
            // Does the json_encode call sit inside an e(...) wrapper?
            // Crude but effective: look for e( before json_encode( and
            // a matching ) after it in the same attribute string.
            $ePos = strpos($wholeAttr, 'e(');
            $jPos = strpos($wholeAttr, 'json_encode');
            if ($ePos === false || $ePos > $jPos) {
                $errors[] = [
                    'file'    => $rel,
                    'line'    => $line,
                    'message' => "data-* attribute contains unescaped "
                               . "json_encode() output. Wrap in e(). "
                               . "See Pattern 13.",
                ];
            }
        }
    }

    // -----------------------------------------------------------------
    // Rule 5 - unbalanced data-* attribute quotes
    // -----------------------------------------------------------------
    //
    // For every line, find each `data-name=` and confirm the
    // opening quote has a matching close on the same line. Multi-
    // line attributes are not supported by this checker; they are
    // rare in this codebase and their absence is a signal worth
    // raising.
    foreach (explode("\n", $src) as $idx => $line) {
        if (!preg_match_all('/\bdata-[a-z0-9-]+\s*=\s*(["\'])/', $line, $qm, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($qm[0] as $mi => $match) {
            $quoteChar  = $qm[1][$mi][0];
            $afterQuote = $match[1] + strlen($match[0]);
            $rest       = substr($line, $afterQuote);
            if (!str_contains($rest, $quoteChar)) {
                $errors[] = [
                    'file'    => $rel,
                    'line'    => $idx + 1,
                    'message' => "Unbalanced data-* attribute quote. "
                               . "Opening {$quoteChar} has no matching "
                               . "close on the same line.",
                ];
            }
        }
    }

}

// -----------------------------------------------------------------
// Output
// -----------------------------------------------------------------

echo "Checking HTML integrity...\n";
echo "  Views checked:  {$checked}\n";
echo "  Errors:         " . count($errors) . "\n";
echo "  Warnings:       " . count($warnings) . "\n";

if ($errors === []) {
    if ($warnings !== []) {
        echo "\nWARNINGS:\n";
        foreach ($warnings as $w) {
            echo "  {$w['file']}:{$w['line']}\n";
            echo "    {$w['message']}\n";
        }
    }
    echo "\nOK - HTML is structurally clean.\n";
    exit(0);
}

echo "\n";
foreach ($errors as $err) {
    $line = $err['line'] > 0 ? ':' . $err['line'] : '';
    echo "  {$err['file']}{$line}\n";
    echo "    {$err['message']}\n";
}

if ($warnings !== []) {
    echo "\nWARNINGS:\n";
    foreach ($warnings as $w) {
        echo "  {$w['file']}:{$w['line']}\n";
        echo "    {$w['message']}\n";
    }
}

echo "\nFAILED: " . count($errors) . " structural error(s).\n";
exit(1);
