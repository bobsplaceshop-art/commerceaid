<?php
/**
 * Setup tab: Business.
 *
 * @var array  $store
 * @var array  $errors
 * @var array  $old
 * @var string $csrf
 */
$store  = $store  ?? [];
$errors = $errors ?? [];
$old    = $old    ?? [];
$csrf   = $csrf   ?? '';

use CorporateAid\Commerce\Support\StoreFieldReader;
$val = fn(string $k, $d = '') => e((string) ($old[$k] ?? StoreFieldReader::read($store, $k, $d)));
$err = fn(string $k) => isset($errors[$k])
    ? '<div class="text-danger small mt-1">' . e((string) $errors[$k]) . '</div>'
    : '';
?>

<form method="POST" action="/commerce/ca-admin/setup/business" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Business details</h2>
        </div>
        <div class="ra-card-body">

            <div class="mb-3">
                <label for="name" class="form-label">Business name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= $val('name') ?>">
                <?= $err('name') ?>
            </div>

            <div class="mb-3">
                <label for="slug" class="form-label">Store slug <span class="text-danger">*</span></label>
                <input type="text" id="slug" name="slug" class="form-control" required
                       pattern="[a-z0-9][a-z0-9-]{1,78}[a-z0-9]"
                       value="<?= $val('slug') ?>">
                <div class="form-text">Used in URLs. Lowercase letters, digits, hyphens. Cannot be changed later.</div>
                <?= $err('slug') ?>
            </div>

            <div class="mb-3">
                <label for="legal_name" class="form-label">Legal name</label>
                <input type="text" id="legal_name" name="legal_name" class="form-control"
                       value="<?= $val('legal_name') ?>"
                       placeholder="Anaya Global Ltd">
                <div class="form-text">Appears on invoices and receipts. Leave blank to use the business name.</div>
            </div>

            <div class="mb-3">
                <label for="tagline" class="form-label">Tagline</label>
                <input type="text" id="tagline" name="tagline" class="form-control"
                       value="<?= $val('tagline') ?>"
                       placeholder="Global Commerce Platform">
            </div>

        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save</button>
    </div>
</form>
