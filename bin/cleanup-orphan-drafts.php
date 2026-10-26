<?php
declare(strict_types=1);

/**
 * Deletes draft rows whose parent_id points at a draft that
 * no longer exists. Such orphans make DraftPublisher refuse
 * to publish with "child draft #X references missing parent
 * draft #Y".
 *
 * Usage (from Laragon Terminal or WSL):
 *   php bin/cleanup-orphan-drafts.php --dry-run
 *   php bin/cleanup-orphan-drafts.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CorporateAid\Core\App;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Core\Database;

$dryRun = in_array('--dry-run', $argv, true);

$app = App::boot(dirname(__DIR__));
$c = $app->container();
$c->get(StoreContext::class)->set(1, 'anaya', 'cli');
$db = $c->get(Database::class);

echo $dryRun ? "DRY RUN — nothing will be deleted.\n\n" : "Deleting orphan draft rows.\n\n";

// Loop: keep finding and deleting until no more orphans.
// A deleted orphan can itself be the parent of another
// orphan, so one pass is not enough.
$pass = 0;
$totalDeleted = 0;

while (true) {
    $pass++;
    if ($pass > 100) {
        echo "Too many passes, aborting.\n";
        exit(1);
    }

    $orphans = $db->select(
        "SELECT d.id, d.block_type, d.parent_id
           FROM commerce_page_drafts d
          WHERE d.parent_id IS NOT NULL
            AND NOT EXISTS (
                SELECT 1 FROM commerce_page_drafts p
                 WHERE p.id = d.parent_id
                   AND p.store_id = d.store_id
                   AND p.page_slug = d.page_slug
                   AND p.theme_slug = d.theme_slug
            )"
    );

    if (empty($orphans)) {
        break;
    }

    echo "Pass {$pass}: found " . count($orphans) . " orphan(s).\n";
    foreach ($orphans as $o) {
        echo "  id={$o['id']} type={$o['block_type']} parent={$o['parent_id']}\n";
        if (!$dryRun) {
            $db->execute('DELETE FROM commerce_page_drafts WHERE id = ?', [$o['id']]);
            $totalDeleted++;
        }
    }
    echo "\n";

    if ($dryRun) {
        break;
    }
}

if ($dryRun) {
    echo "DRY RUN complete. Re-run without --dry-run to delete.\n";
} else {
    echo "Done. Total drafts deleted: {$totalDeleted}.\n";
}
