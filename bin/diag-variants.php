<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

try {
    $app = \CorporateAid\Core\App::boot($basePath);
    $c   = $app->container();

    // Set a store context so repositories don't throw.
    $db = $c->get(\CorporateAid\Core\Database::class);
    $row = $db->selectOne("SELECT `id`, `slug` FROM `commerce_stores` ORDER BY `id` ASC LIMIT 1");
    if ($row === null) {
        fwrite(STDERR, "No stores found.\n");
        exit(1);
    }
    $storeId = (int) $row['id'];
    $slug    = (string) $row['slug'];
    $c->get(\CorporateAid\Commerce\Context\StoreContext::class)->set($storeId, $slug, 'cli');

    echo "Store context: id={$storeId} slug={$slug}\n\n";

    $media = $c->get(\CorporateAid\Commerce\Repositories\MediaRepository::class);
    $files = $c->get(\CorporateAid\Platform\Repositories\FileRepository::class);
    $fileSvc = $c->get(\CorporateAid\Platform\Services\FileService::class);

    $id = (int) ($argv[1] ?? 154);
    $file = $files->findById($id);
    if ($file === null) {
        fwrite(STDERR, "File {$id} not found in core_files.\n");
        exit(1);
    }

    echo "=== File record ===\n";
    echo "  id:         {$file->id}\n";
    echo "  uuid:       {$file->uuid}\n";
    echo "  mime_type:  {$file->mimeType}\n";
    echo "  storage:    {$file->storagePath}\n";
    echo "  deleted:    " . ($file->deletedAt ?? 'NULL') . "\n";

    $absolute = $fileSvc->absolutePath($file);
    echo "\n=== Source on disk ===\n";
    echo "  path:       {$absolute}\n";
    echo "  is_file:    " . (is_file($absolute) ? 'YES' : 'NO') . "\n";
    echo "  readable:   " . (is_readable($absolute) ? 'YES' : 'NO') . "\n";
    echo "  size:       " . (is_file($absolute) ? filesize($absolute) : 0) . "\n";

    // Pre-state of DB variants
    $preRows = $db->select(
        "SELECT cv.variant_name, cv.format, vf.storage_path
           FROM core_media_variants cv
           JOIN core_files vf ON vf.id = cv.variant_file_id
          WHERE cv.source_file_id = ?",
        [$id]
    );
    echo "\n=== Pre-state: variant rows = " . count($preRows) . " ===\n";
    foreach ($preRows as $r) {
        echo "  {$r['variant_name']}.{$r['format']} -> {$r['storage_path']}\n";
    }

    echo "\n=== Calling ensureVariants() ===\n";
    $start = microtime(true);
    try {
        $media->ensureVariants($file);
        echo "  returned cleanly in " . round((microtime(true) - $start) * 1000) . "ms\n";
    } catch (\Throwable $e) {
        echo "  EXCEPTION: " . $e->getMessage() . "\n";
        echo "  at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }

    // Post-state
    $postRows = $db->select(
        "SELECT cv.variant_name, cv.format, vf.storage_path
           FROM core_media_variants cv
           JOIN core_files vf ON vf.id = cv.variant_file_id
          WHERE cv.source_file_id = ?",
        [$id]
    );
    echo "\n=== Post-state: variant rows = " . count($postRows) . " ===\n";
    foreach ($postRows as $r) {
        echo "  {$r['variant_name']}.{$r['format']} -> {$r['storage_path']}\n";
    }

    echo "\n=== Variant files on disk ===\n";
    $parts = explode('/', trim($file->storagePath, '/'));
    $category = $parts[2] ?? 'general';
    $variantDir = $fileSvc->basePath() . '/storage/uploads/' . $category . '/variants';
    echo "  dir: {$variantDir}\n";
    if (is_dir($variantDir)) {
        $files = glob($variantDir . '/*') ?: [];
        foreach ($files as $f) {
            echo "  " . basename($f) . "  (" . filesize($f) . " bytes)\n";
        }
        if ($files === []) echo "  (empty)\n";
    } else {
        echo "  (dir does not exist)\n";
    }

} catch (\Throwable $e) {
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
