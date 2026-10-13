<?php
/**
 * Media library grid.
 *
 * @var array $result      ['items' => MediaItem[], 'total' => int, 'page' => int, 'per_page' => int, 'pages' => int]
 * @var array $filters     ['q' => string, 'mime' => string, 'category' => string, 'sort' => string]
 * @var array $categories  array<int, string> of distinct file categories for this store
 * @var array $sortOptions array<int, string>
 * @var string|null $flashOk
 * @var string|null $flashErr
 */
$result      = $result      ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 24, 'pages' => 0];
$filters     = $filters     ?? [];
$categories  = $categories  ?? [];
$sortOptions = $sortOptions ?? ['newest', 'oldest', 'name', 'size'];
$flashOk     = $flashOk     ?? null;
$flashErr    = $flashErr    ?? null;

$items = $result['items'];
$total = $result['total'];
$page  = $result['page'];
$pages = $result['pages'];

$hasFilters = ($filters['q'] ?? '') !== ''
    || ($filters['mime'] ?? '') !== ''
    || ($filters['category'] ?? '') !== '';

$pageUrl = function (int $p) use ($filters): string {
    $q = array_merge($filters, ['page' => $p]);
    $q = array_filter($q, fn ($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($q);
};

$mimeOptions = [
    ''              => 'All types',
    'image/'        => 'Images',
    'image/png'     => 'PNG',
    'image/jpeg'    => 'JPEG',
    'image/webp'    => 'WebP',
    'image/gif'     => 'GIF',
];

$sortLabels = [
    'newest' => 'Newest first',
    'oldest' => 'Oldest first',
    'name'   => 'Name (A–Z)',
    'size'   => 'Largest first',
];
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Media Library</h1>
        <p class="muted mb-0">
            <?= (int) $total ?> file<?= $total === 1 ? '' : 's' ?>
            <?php if ($hasFilters): ?>
                &middot; filtered
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($flashOk !== null && $flashOk !== ''): ?>
    <div class="ra-alert ra-alert-success mb-4"><?= e($flashOk) ?></div>
<?php endif; ?>
<?php if ($flashErr !== null && $flashErr !== ''): ?>
    <div class="ra-alert ra-alert-error mb-4"><?= e($flashErr) ?></div>
<?php endif; ?>

<form method="GET" action="/commerce/ca-admin/media" class="ra-card mb-4" id="ra-media-filters">
    <div class="ra-card-body">
        <button type="button" class="ra-filter-toggle" data-ra-filter-toggle>
            <i class="bi bi-funnel"></i> Filters
        </button>
        <div class="ra-filter-bar">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small mb-1">Search</label>
                <input type="text"
                       name="q"
                       id="ra-media-search"
                       class="form-control form-control-sm"
                       value="<?= e((string) ($filters['q'] ?? '')) ?>"
                       placeholder="Filename"
                       autocomplete="off">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Type</label>
                <select name="mime" class="form-select form-select-sm" data-ra-filter>
                    <?php foreach ($mimeOptions as $val => $label): ?>
                        <option value="<?= e((string) $val) ?>"
                                <?= ($filters['mime'] ?? '') === (string) $val ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Category</label>
                <select name="category" class="form-select form-select-sm" data-ra-filter>
                    <option value="">All categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>"
                                <?= ($filters['category'] ?? '') === $cat ? 'selected' : '' ?>>
                            <?= e($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Sort</label>
                <select name="sort" class="form-select form-select-sm" data-ra-filter>
                    <?php foreach ($sortOptions as $opt): ?>
                        <option value="<?= e($opt) ?>"
                                <?= ($filters['sort'] ?? 'newest') === $opt ? 'selected' : '' ?>>
                            <?= e($sortLabels[$opt] ?? $opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        </div>
    </div>
</form>

<?php if (empty($items)): ?>

    <?= view_partial('admin.media._empty', ['hasFilters' => $hasFilters]) ?>

<?php else: ?>

    <ul class="ra-media-grid" id="ra-media-grid">
        <?php foreach ($items as $item): ?>
            <?= view_partial('admin.media._grid-item', ['item' => $item]) ?>
        <?php endforeach; ?>
    </ul>

    <?php if ($pages > 1): ?>
        <nav class="mt-4" aria-label="Pagination">
            <ul class="pagination pagination-sm justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= e($pageUrl($page - 1)) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);

                if ($start > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= e($pageUrl(1)) ?>">1</a></li>
                    <?php if ($start > 2): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif;
                endif;

                for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
                    </li>
                <?php endfor;

                if ($end < $pages): ?>
                    <?php if ($end < $pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= e($pageUrl($pages)) ?>"><?= $pages ?></a></li>
                <?php endif; ?>

                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= e($pageUrl($page + 1)) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<?= view_partial('admin.media._delete-modal') ?>
<?= view_partial('admin.media._detail-modal') ?>

<script src="<?= versioned_asset('js/media-library.js') ?>"></script>
