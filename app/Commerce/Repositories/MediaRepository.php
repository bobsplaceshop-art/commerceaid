<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\MediaItem;
use CorporateAid\Commerce\Models\MediaMeta;
use CorporateAid\Commerce\Support\GeneratedVariant;
use CorporateAid\Commerce\Support\MediaProcessor;
use CorporateAid\Commerce\Support\MediaVariantSet;
use CorporateAid\Core\Database;
use CorporateAid\Platform\Models\File;
use CorporateAid\Platform\Repositories\FileRepository;
use CorporateAid\Platform\Services\FileService;

/**
 * Media repository.
 *
 * Orchestrates the upload pipeline: validation, storage,
 * EXIF stripping, image metadata, and media row assembly.
 *
 * Does NOT extend CommerceRepository. The tables it touches
 * (core_files, core_media_meta) are Platform tables with no
 * store_id column. CommerceRepository requires a resolved
 * store context and would throw on any non-store caller
 * (a CLI cleanup job, an admin-side audit). This class works
 * with a Database directly.
 *
 * Dependencies:
 *   FileService     - Platform's validated storage pipeline
 *   FileRepository  - core_files CRUD
 *   MediaProcessor  - GD-based EXIF stripping
 *   Database        - core_media_meta queries
 *
 * The full upload flow is storeFromUpload():
 *   1. Delegate to FileService::store() with a category.
 *   2. Read dimensions and re-encode the stored file via
 *      MediaProcessor (strips EXIF).
 *   3. If the re-encode fails, reject the file and remove
 *      the physical asset.
 *   4. Insert a core_media_meta row with the dimensions
 *      and context.
 *   5. Return a MediaItem.
 *
 * See docs/stages/008g.4a_upload-core-plan.md.
 */
final class MediaRepository
{
    public function __construct(
        private Database $db,
        private FileService $files,
        private FileRepository $fileRepo,
        private MediaProcessor $processor,
    ) {
    }

    // =================================================================
    // Upload
    // =================================================================

    /**
     * Process an uploaded file into the media library.
     *
     * $storeId is required. Every upload is linked to a
     * store via core_entity_files so the media library page
     * can scope its listing.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @param int    $storeId   the store that owns this file
     * @param string $category  routing category for storage subdirectory
     * @param string $context   media meta context ('store-logo', etc.)
     * @param int    $maxMb     size cap in MB
     * @param int|null $userId  uploader
     * @return MediaItem|array<int,string>  MediaItem on success, errors on failure
     */
    public function storeFromUpload(
        array $file,
        int $storeId,
        string $category,
        string $context,
        int $maxMb = FileService::DEFAULT_MAX_MB,
        ?int $userId = null
    ): MediaItem|array {
        // ---- 0. Cap the caller's size by the context spec ----
        // The context is authoritative. A caller may pass a
        // smaller cap, but never a larger one than the context
        // declares. Without this, a bug in the caller could
        // let a 5 MB file through a 1 MB field.
        $spec = MediaVariantSet::for($context);
        if ($spec->sourceMaxMb < $maxMb) {
            $maxMb = $spec->sourceMaxMb;
        }

        // ---- 1. Store via Platform's validated pipeline ----
        $stored = $this->files->store($file, $userId, $category, $maxMb);

        if (is_array($stored)) {
            // Validation or storage failed. Return the errors.
            return $stored;
        }

        // $stored is now a File model.
        $absolutePath = $this->files->absolutePath($stored);

        // ---- 2. Strip EXIF and read dimensions ----
        $dimensions = null;
        if (str_starts_with($stored->mimeType, 'image/')) {
            $dimensions = $this->processor->stripMetadata(
                $absolutePath,
                $stored->mimeType
            );

            if ($dimensions === null && $this->isRaster($stored->mimeType)) {
                // Re-encode failed for a raster image. Reject
                // the upload. Storing a file with un-stripped
                // EXIF is worse than no file.
                $this->files->softDelete($stored->id);
                return ['The image could not be processed.'];
            }
        }

        // ---- 3. Write the meta row ----
        $meta = null;
        if ($dimensions !== null) {
            $aspect = $dimensions['height'] > 0
                ? $dimensions['width'] / $dimensions['height']
                : null;

            $metaId = (int) $this->db->insert(
                "INSERT INTO `core_media_meta`
                    (`file_id`, `width`, `height`, `aspect_ratio`, `context`)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    `width`        = VALUES(`width`),
                    `height`       = VALUES(`height`),
                    `aspect_ratio` = VALUES(`aspect_ratio`),
                    `context`      = VALUES(`context`)",
                [
                    $stored->id,
                    $dimensions['width'],
                    $dimensions['height'],
                    $aspect !== null ? round($aspect, 3) : null,
                    $context,
                ]
            );

            $meta = $this->findMetaByFileId($stored->id);
        }

        // ---- 4. Generate size variants ----
        // Only for raster images. Non-image uploads skip this
        // step entirely.
        if ($meta !== null && $this->isRaster($stored->mimeType)) {
            $this->registerVariants(
                $stored,
                $absolutePath,
                $stored->mimeType,
                $context,
                $category
            );
        }

        // ---- 5. Link to the store ----
        // Every upload writes a core_entity_files row so the
        // media library page can scope its listing. Idempotent
        // via the UNIQUE key.
        if ($storeId > 0) {
            $this->linkToStore($stored->id, $storeId, $context, $userId);
        }

        return new MediaItem($stored, $meta);
    }

    /**
     * Write a core_entity_files link from a file to a store.
     *
     * Idempotent via the UNIQUE key on
     * (file_id, entity_type, entity_id, file_category).
     * INSERT IGNORE turns a duplicate into a silent skip, so
     * re-uploading the same file for the same store and
     * context is a no-op.
     */
    public function linkToStore(
        int $fileId,
        int $storeId,
        string $category,
        ?int $userId = null
    ): void {
        if ($fileId <= 0 || $storeId <= 0) {
            return;
        }

        $this->db->execute(
            "INSERT IGNORE INTO `core_entity_files`
                (`file_id`, `entity_type`, `entity_id`,
                 `file_category`, `linked_by`)
             VALUES (?, 'store', ?, ?, ?)",
            [$fileId, $storeId, $category, $userId]
        );
    }

    /**
     * Generate and register the size variants declared by the
     * context. Writes each variant to disk, inserts a
     * core_files row, and links it to the source via
     * core_media_variants.
     *
     * Failures on individual variants are logged and skipped.
     * A partial variant set is better than a failed upload.
     */
    private function registerVariants(
        File $source,
        string $sourceAbsolutePath,
        string $sourceMime,
        string $context,
        string $category
    ): void {
        $spec = MediaVariantSet::for($context);

        $relativeVariantDir = 'storage/uploads/'
            . trim($category, '/')
            . '/variants';
        $absoluteVariantDir = rtrim($this->files->basePath(), '/')
            . '/'
            . $relativeVariantDir;

        // A fresh prefix for this upload's variant set. Every
        // variant file gets prefix-name.ext. The prefix is a
        // UUID so it never collides with another upload's
        // variants.
        $filenamePrefix = generate_uuid();

        $generated = $this->processor->generateVariants(
            $sourceAbsolutePath,
            $sourceMime,
            $spec,
            $absoluteVariantDir,
            $filenamePrefix
        );

        if ($generated === []) {
            return;
        }

        $relativeVariantDir = rtrim($relativeVariantDir, '/');

        foreach ($generated as $variant) {
            $relativePath = $relativeVariantDir . '/' . $variant->storedName;

            // Register the variant file in core_files.
            try {
                $variantFileId = $this->fileRepo->create(
                    uuid:         $variant->uuid,
                    originalName: $source->originalName . ' (' . $variant->name . ')',
                    storedName:   $variant->storedName,
                    storagePath:  $relativePath,
                    mimeType:     $variant->mimeType,
                    extension:    $variant->extension,
                    sizeBytes:    $variant->sizeBytes,
                    sha256:       $variant->sha256,
                    uploadedBy:   $source->uploadedBy,
                );
            } catch (\Throwable $e) {
                // If registering the file fails, remove the
                // disk artifact so it does not linger.
                @unlink($variant->absolutePath);
                continue;
            }

            // Link the variant to the source.
            try {
                $this->db->execute(
                    "INSERT INTO `core_media_variants`
                        (`source_file_id`, `variant_file_id`,
                         `variant_name`, `width`, `height`, `format`)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        `variant_file_id` = VALUES(`variant_file_id`),
                        `width`           = VALUES(`width`),
                        `height`          = VALUES(`height`)",
                    [
                        $source->id,
                        $variantFileId,
                        $variant->name,
                        $variant->width,
                        $variant->height,
                        $variant->format,
                    ]
                );
            } catch (\Throwable $e) {
                // If linking fails, soft-delete the orphan
                // variant file so it does not accumulate.
                $this->fileRepo->softDelete($variantFileId);
            }
        }
    }

    // =================================================================
    // Usage queries
    // =================================================================

    /**
     * How many entities reference this file. Used by the
     * media page to decide whether a file can be deleted
     * without leaving a dangling reference.
     */
    public function usageCountForFile(int $fileId): int
    {
        if ($fileId <= 0) {
            return 0;
        }

        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `core_entity_files`
              WHERE `file_id` = ?",
            [$fileId]
        );
    }

    /**
     * Every entity reference for a file. Used by the delete
     * modal to show "used by: store logo, product #42".
     *
     * @return array<int, array{entity_type:string, entity_id:int, file_category:string}>
     */
    public function findUsageForFile(int $fileId): array
    {
        if ($fileId <= 0) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT `entity_type`, `entity_id`, `file_category`
               FROM `core_entity_files`
              WHERE `file_id` = ?
              ORDER BY `entity_type` ASC, `entity_id` ASC",
            [$fileId]
        );

        return array_map(
            fn ($r) => [
                'entity_type'   => (string) $r['entity_type'],
                'entity_id'     => (int)    $r['entity_id'],
                'file_category' => (string) $r['file_category'],
            ],
            $rows
        );
    }

    // =================================================================
    // Reads
    // =================================================================

    public function findByUuid(string $uuid): ?MediaItem
    {
        if ($uuid === '') {
            return null;
        }

        $file = $this->fileRepo->findByUuid($uuid);
        if ($file === null || $file->isDeleted()) {
            return null;
        }

        return new MediaItem($file, $this->findMetaByFileId($file->id));
    }

    public function findById(int $fileId): ?MediaItem
    {
        if ($fileId <= 0) {
            return null;
        }

        $file = $this->fileRepo->findById($fileId);
        if ($file === null || $file->isDeleted()) {
            return null;
        }

        return new MediaItem($file, $this->findMetaByFileId($file->id));
    }

    /**
     * @param array<int,int> $fileIds
     * @return array<int, MediaItem>
     */
    public function findByIds(array $fileIds): array
    {
        $clean = [];
        foreach ($fileIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = true;
            }
        }
        if ($clean === []) {
            return [];
        }

        $ids = array_keys($clean);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = $this->db->select(
            "SELECT * FROM `core_files`
              WHERE `id` IN ({$placeholders})
                AND `deleted_at` IS NULL",
            $ids
        );

        $files = [];
        foreach ($rows as $row) {
            $files[] = File::fromRow($row);
        }
        if ($files === []) {
            return [];
        }

        // Bulk-load meta rows in one query.
        $metasByFileId = $this->findMetasByFileIds($ids);

        $out = [];
        foreach ($files as $f) {
            $out[] = new MediaItem($f, $metasByFileId[$f->id] ?? null);
        }
        return $out;
    }

    // =================================================================
    // Paginated listing for the media library page
    // =================================================================

    /**
     * List files scoped to a store, with optional filters and
     * pagination.
     *
     * Files are scoped by joining core_entity_files on
     * entity_type='store' and entity_id=$storeId. A file
     * uploaded by another store does not appear.
     *
     * @param array{q?:string, mime?:string, category?:string, sort?:string} $filters
     * @return array{items:array<int,MediaItem>, total:int, page:int, per_page:int, pages:int}
     */
    public function paginateForStore(
        int $storeId,
        array $filters = [],
        int $page = 1,
        int $perPage = 24
    ): array {
        if ($storeId <= 0) {
            return [
                'items'    => [],
                'total'    => 0,
                'page'     => 1,
                'per_page' => $perPage,
                'pages'    => 1,
            ];
        }

        $perPage = max(1, min($perPage, 100));
        $page    = max(1, $page);

        $where  = [
            "ef.`entity_type` = 'store'",
            "ef.`entity_id` = ?",
            "f.`deleted_at` IS NULL",
        ];
        $params = [$storeId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[]  = "f.`original_name` LIKE ?";
            $params[] = '%' . $q . '%';
        }

        $mime = trim((string) ($filters['mime'] ?? ''));
        if ($mime !== '') {
            $where[]  = "f.`mime_type` LIKE ?";
            $params[] = $mime . '%';
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $where[]  = "ef.`file_category` = ?";
            $params[] = $category;
        }

        $whereSql = implode(' AND ', $where);

        $sort = (string) ($filters['sort'] ?? 'newest');
        $orderBy = match ($sort) {
            'oldest' => "f.`created_at` ASC, f.`id` ASC",
            'name'   => "f.`original_name` ASC",
            'size'   => "f.`size_bytes` DESC",
            default  => "f.`created_at` DESC, f.`id` DESC",
        };

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*)
               FROM `core_files` f
               INNER JOIN `core_entity_files` ef ON ef.`file_id` = f.`id`
              WHERE {$whereSql}",
            $params
        );

        $pages  = max(1, (int) ceil($total / $perPage));
        $page   = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT f.*
               FROM `core_files` f
               INNER JOIN `core_entity_files` ef ON ef.`file_id` = f.`id`
              WHERE {$whereSql}
              ORDER BY {$orderBy}
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $fileIds = [];
        $files   = [];
        foreach ($rows as $row) {
            $files[]   = File::fromRow($row);
            $fileIds[] = (int) $row['id'];
        }
        $metasByFileId = $this->findMetasByFileIds($fileIds);

        $items = [];
        foreach ($files as $f) {
            $items[] = new MediaItem($f, $metasByFileId[$f->id] ?? null);
        }

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ];
    }

    /**
     * Distinct file categories that exist for a store. Used
     * by the media page's category filter dropdown.
     *
     * @return array<int, string>
     */
    public function categoriesForStore(int $storeId): array
    {
        if ($storeId <= 0) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT DISTINCT `file_category`
               FROM `core_entity_files`
              WHERE `entity_type` = 'store' AND `entity_id` = ?
              ORDER BY `file_category` ASC",
            [$storeId]
        );

        return array_map(fn ($r) => (string) $r['file_category'], $rows);
    }

    // =================================================================
    // Mutations on existing media
    // =================================================================

    /**
     * Resolve a specific size variant for a source file,
     * addressed by the source's UUID plus the variant name
     * and format.
     *
     * Returns the variant file wrapped as a MediaItem, or
     * null if the source does not exist, the variant does
     * not exist, or either file is soft-deleted.
     */
    public function findVariantBySourceUuid(
        string $sourceUuid,
        string $variantName,
        string $format
    ): ?MediaItem {
        if ($sourceUuid === '' || $variantName === '' || $format === '') {
            return null;
        }

        $row = $this->db->selectOne(
            "SELECT vf.*
               FROM `core_media_variants` cv
               JOIN `core_files` sf ON sf.`id` = cv.`source_file_id`
               JOIN `core_files` vf ON vf.`id` = cv.`variant_file_id`
              WHERE sf.`uuid` = ?
                AND sf.`deleted_at` IS NULL
                AND cv.`variant_name` = ?
                AND cv.`format` = ?
                AND vf.`deleted_at` IS NULL
              LIMIT 1",
            [$sourceUuid, $variantName, $format]
        );

        if ($row === null) {
            return null;
        }

        $file = File::fromRow($row);
        return new MediaItem($file, $this->findMetaByFileId($file->id));
    }

    /**
     * Soft-delete a file. Delegates to FileService, which
     * unlinks every entity reference and soft-deletes the
     * file row.
     */
    public function softDelete(int $fileId): void
    {
        if ($fileId <= 0) {
            return;
        }
        $this->files->softDelete($fileId);
    }

    /**
     * Update alt text and/or credit. Either may be null to
     * clear the field.
     */
    public function updateMetaText(
        int $fileId,
        ?string $altText,
        ?string $credit
    ): void {
        if ($fileId <= 0) {
            return;
        }

        $this->db->execute(
            "UPDATE `core_media_meta`
                SET `alt_text` = ?, `credit` = ?
              WHERE `file_id` = ?",
            [
                $altText !== '' ? $altText : null,
                $credit !== '' ? $credit : null,
                $fileId,
            ]
        );
    }

    // =================================================================
    // Internals
    // =================================================================

    private function findMetaByFileId(int $fileId): ?MediaMeta
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_media_meta` WHERE `file_id` = ? LIMIT 1",
            [$fileId]
        );
        return $row !== null ? MediaMeta::fromRow($row) : null;
    }

    /**
     * @param array<int,int> $fileIds
     * @return array<int, MediaMeta>
     */
    private function findMetasByFileIds(array $fileIds): array
    {
        if ($fileIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $rows = $this->db->select(
            "SELECT * FROM `core_media_meta`
              WHERE `file_id` IN ({$placeholders})",
            $fileIds
        );

        $out = [];
        foreach ($rows as $row) {
            $meta = MediaMeta::fromRow($row);
            $out[$meta->fileId] = $meta;
        }
        return $out;
    }

    private function isRaster(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ], true);
    }
}
