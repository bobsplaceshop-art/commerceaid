<?php
/**
 * @var array|null $results
 */
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">Performance Check</h1>
        <p class="muted mb-0">
            Hits every GET route in the app and reports timings.
            Warm overages are flagged. Budgets:
            storefront 3s warm / 15s cold; admin 5s warm / 30s cold.
        </p>
    </div>
    <form method="POST" action="/commerce/ca-admin/system/performance/run">
        <?= csrf_field() ?>
        <button type="submit" class="ra-btn ra-btn-primary">
            <i class="bi bi-play-fill me-1"></i> Run check
        </button>
    </form>
</div>

<?php if ($results === null): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <p class="muted mb-0">
                No check has been run yet. Click <strong>Run check</strong>
                to measure every route in the app.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <p class="muted small mb-1">Last run: <?= e($results['started_at']) ?> (<?= (int) $results['duration_ms'] ?>ms total)</p>
                    <p class="mb-0">
                        <strong><?= (int) $results['totals']['pass'] ?></strong> passed,
                        <strong><?= (int) $results['totals']['fail'] ?></strong> failed
                        (<?= (int) $results['totals']['routes'] ?> routes).
                    </p>
                </div>
                <span class="ra-badge <?= $results['totals']['fail'] === 0 ? 'ra-badge-success' : 'ra-badge-danger' ?>">
                    <?= $results['totals']['fail'] === 0 ? 'All healthy' : $results['totals']['fail'] . ' failing' ?>
                </span>
            </div>
        </div>
    </div>

    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="cards">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Path</th>
                        <th class="ra-hide-sm">HTTP</th>
                        <th style="text-align:right;">Cold</th>
                        <th style="text-align:right;">Warm</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($results['results'] as $r): ?>
                    <tr>
                        <td data-col="half">
                            <?php if ($r['pass']): ?>
                                <span class="ra-badge ra-badge-success">OK</span>
                            <?php else: ?>
                                <span class="ra-badge ra-badge-danger">FAIL</span>
                            <?php endif; ?>
                        </td>
                        <td data-col="full">
                            <code class="text-muted"><?= e($r['path']) ?></code>
                            <?php if ($r['error']): ?>
                                <div class="text-danger small mt-1"><?= e($r['error']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-col="half">
                            <?= (int) $r['status'] ?>
                        </td>
                        <td data-col="half" style="text-align:right;">
                            <?= (int) $r['cold_ms'] ?>ms
                            <span class="text-muted small">/ <?= (int) $r['cold_budget'] ?></span>
                        </td>
                        <td data-col="half" style="text-align:right;">
                            <?= (int) $r['warm_ms'] ?>ms
                            <span class="text-muted small">/ <?= (int) $r['warm_budget'] ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
