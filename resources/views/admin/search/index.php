<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Search Results</h1>
        <p class="muted mb-0">
            <?php if ($q === ''): ?>
                Enter a search term above.
            <?php else: ?>
                Showing results for <strong><?= e($q) ?></strong>
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($q !== ''): ?>
    <div class="row">
        <!-- Products -->
        <div class="col-md-6 mb-4">
            <div class="ra-card h-100">
                <h2 class="h5 mb-3">Products</h2>
                <?php if (empty($products)): ?>
                    <p class="text-muted small">No products found.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($products as $p): ?>
                            <li class="mb-3 pb-3 border-bottom border-secondary" style="border-color: var(--ra-border) !important;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <a href="/commerce/ca-admin/products/<?= (int)$p->id ?>/edit" class="fw-bold text-decoration-none text-light">
                                            <?= e($p->name) ?>
                                        </a>
                                        <div class="small text-muted mt-1">SKU: <?= e($p->sku) ?></div>
                                    </div>
                                    <span class="ra-badge ra-badge-muted"><?= number_format($p->price, 2) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Orders -->
        <div class="col-md-6 mb-4">
            <div class="ra-card h-100">
                <h2 class="h5 mb-3">Orders</h2>
                <?php if (empty($orders)): ?>
                    <p class="text-muted small">No orders found.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($orders as $o): ?>
                            <li class="mb-3 pb-3 border-bottom border-secondary" style="border-color: var(--ra-border) !important;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <a href="/commerce/ca-admin/orders/<?= (int)$o->id ?>" class="fw-bold text-decoration-none text-light">
                                            <?= e($o->orderNumber) ?>
                                        </a>
                                        <div class="small text-muted mt-1"><?= e($o->customerName ?? 'Guest') ?></div>
                                    </div>
                                    <span class="ra-badge <?= $o->status === 'paid' ? 'ra-badge-success' : 'ra-badge-muted' ?>"><?= strtoupper($o->status) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
