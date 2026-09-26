<?php
/**
 * Setup tab: Shipping.
 *
 * Form only for now. Backing tables (commerce_shipping_*) ship in
 * Stage 007.
 *
 * @var array  $errors
 * @var array  $old
 * @var string $csrf
 */
$errors = $errors ?? [];
$old    = $old    ?? [];
$csrf   = $csrf   ?? '';
?>

<form method="POST" action="/commerce/ca-admin/setup/shipping" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="ra-alert ra-alert-info mb-4">
        <i class="bi bi-info-circle me-1"></i>
        This section is under construction. Settings are accepted but not yet persisted.
        Full shipping zones and rates ship in a future stage.
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Shipping options</h2>
        </div>
        <div class="ra-card-body">

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="free_shipping_enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="free_shipping_enabled" name="free_shipping_enabled" value="1">
                    <label class="form-check-label" for="free_shipping_enabled">
                        <strong>Offer free shipping on qualifying orders</strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    Applied automatically when an order total exceeds the threshold below.
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="free_shipping_threshold" class="form-label">Free shipping threshold</label>
                    <div class="input-group">
                        <span class="input-group-text">KSh</span>
                        <input type="number" id="free_shipping_threshold" name="free_shipping_threshold"
                               class="form-control" min="0" step="1" placeholder="5000">
                    </div>
                    <div class="form-text">Order subtotal at or above this value ships free.</div>
                </div>
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="local_pickup_enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="local_pickup_enabled" name="local_pickup_enabled" value="1">
                    <label class="form-check-label" for="local_pickup_enabled">
                        <strong>Enable local pickup</strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    Customers can collect their order from your location instead of having it delivered.
                </div>
            </div>

            <div class="mb-3">
                <label for="pickup_address" class="form-label">Pickup address</label>
                <textarea id="pickup_address" name="pickup_address" class="form-control" rows="3"
                          placeholder="Full address where customers can collect orders"></textarea>
            </div>

        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Zones and rates</h2>
        </div>
        <div class="ra-card-body">
            <p class="muted small mb-0">
                Detailed shipping zones (regions, countries) and rates per zone
                will be configurable once the shipping engine ships. For now, use
                the options above as global defaults.
            </p>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save preferences</button>
    </div>
</form>
