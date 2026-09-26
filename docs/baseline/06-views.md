# 6. Views

Section of: [BASELINE](../BASELINE.md)

## List View Template

    <?php
    /**
     * Docblock describing vars.
     *
     * @var array $result
     */
    $result        = $result        ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
    $currentFilter = $currentFilter ?? 'pending';

    $items = $result['items'];
    $total = $result['total'];
    $page  = $result['page'];
    $pages = $result['pages'];
    ?>

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Page Title</h1>
            <p class="muted mb-0"><?= (int) $total ?> items</p>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="ra-card">
            <div class="ra-card-body text-center py-5">
                <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
                <h2 class="h6 mt-3 mb-1">Empty state headline</h2>
                <p class="muted small mb-3">Explanation text.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="ra-card">
            <div class="ra-table-responsive-wrap">
                <table class="ra-table">
                    <!-- table content -->
                </table>
            </div>
        </div>
    <?php endif; ?>

## Rules

1. Default-guard every variable at the top of the file. Never assume
   a var is set.
2. Escape all output with e().
3. .ra-card is the container for content sections. .ra-card-body adds
   padding.
4. .ra-table-responsive-wrap wraps tables. .ra-hide-sm on secondary
   columns.
5. Buttons: .ra-btn plus a variant (ra-btn-primary, ra-btn-outline,
   ra-btn-danger) for labeled buttons. .ra-icon-btn for icon-only
   buttons.
6. Inline POST forms use method="POST" class="d-inline" plus
   csrf_field() and a submit button.
7. Destructive inline actions use onclick="return confirm('...');".
8. Pagination is an inline block copied verbatim from products/index.php
   (about 30 lines). There is no shared partial.
9. Partials are included via view_partial('admin.foo._modal'). Dot
   notation is preferred. Slash form is also tolerated.
10. Views never render flash. The layout handles it.

## Section Headers

Every list page opens with a header block:

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Page Title</h1>
            <p class="muted mb-0">
                <?= (int) $total ?> item<?= $total === 1 ? '' : 's' ?>
                <?php if ($hasFilters): ?>
                    &middot; filtered
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="..." class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New Item
            </a>
        </div>
    </div>

The flex-wrap gap-2 classes ensure the header wraps on mobile. The
muted text style is the standard subtitle.

## Empty States

Two variants exist in the codebase:

No items at all:

    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No items yet</h2>
            <p class="muted small mb-3">Explanation text.</p>
            <a href="/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Create your first item
            </a>
        </div>
    </div>

Filters applied, no matches:

    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-search" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No matches</h2>
            <p class="muted small mb-3">Try clearing filters or adjusting your search.</p>
            <a href="/list" class="ra-btn ra-btn-outline">Clear filters</a>
        </div>
    </div>

The distinction matters. A first-time user sees "Create your first
item". A searching user sees "Clear filters".

## Table Structure

Every list table follows the same pattern:

    <div class="ra-card">
        <div class="ra-table-responsive-wrap">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Primary column</th>
                        <th class="ra-hide-sm" style="width:180px;">Secondary</th>
                        <th style="width:180px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-box" style="color:var(--ra-muted,#94a3b8);"></i>
                                    <a href="/edit/<?= (int) $item->id ?>" class="text-decoration-none">
                                        <?= e($item->name) ?>
                                    </a>
                                </div>
                            </td>
                            <td class="ra-hide-sm"><code class="muted small"><?= e($item->slug) ?></code></td>
                            <td class="text-end ra-actions-cell">
                                <a href="/edit/<?= (int) $item->id ?>" class="ra-icon-btn" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/delete/<?= (int) $item->id ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-icon-btn" title="Archive"
                                            onclick="return confirm('Archive this item?');">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

Note: the products/index.php table is NOT wrapped in
.ra-table-responsive-wrap. That is a deviation from the directive in
RESPONSIVENESS.md. It has not been fixed yet. All new list pages use
the wrapper.

## Pagination

Copy verbatim from products/index.php lines 273 to end. The block
handles first-page, last-page, current-page, ellipsis on both sides,
and prev/next chevrons. It uses a $pageUrl closure that preserves the
current filters:

    $pageUrl = function (int $p) use ($filters): string {
        $q = array_merge($filters, ['page' => $p]);
        return '?' . http_build_query(array_filter($q, fn ($v) => $v !== '' && $v !== 0 && $v !== false));
    };

The array_filter drops empty-string, zero, and false values so the
URL stays clean.

## Flash

Views do not render flash. Ever. The layout at
resources/views/layouts/app.php handles it globally for every admin
page. If you find yourself writing flash rendering in a view, that
is a bug - remove it.
