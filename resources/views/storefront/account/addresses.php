<?php
/**
 * Customer address book at /account/addresses.
 *
 * Context (from AccountController::addresses):
 *   $customer  ?Customer
 *   $addresses array<int, CustomerAddress>  default first, then by id
 *   $flashOk   ?string  already consumed by the controller from
 *                       the address_success flash key
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */

$customer  = $customer  ?? null;
$addresses = $addresses ?? [];
$flashOk   = $flashOk   ?? null;

$typeLabel = function (string $type): string {
    return match ($type) {
        'billing'  => 'Billing',
        'shipping' => 'Shipping',
        default    => 'Billing &amp; Shipping',
    };
};
?>

<div class="sf-auth-wrap" data-sf-page="account-addresses">
    <div class="sf-auth-card sf-auth-card-wide">

        <h1 class="sf-auth-heading">My Addresses</h1>
        <p class="sf-auth-subhead">
            Add billing and shipping addresses to speed up checkout. One address can serve both purposes.
        </p>

        <?php if ($flashOk !== null): ?>
            <div class="sf-form-alert sf-form-alert-success">
                <?= e((string) $flashOk) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($addresses)): ?>
            <div style="text-align:center;padding:2.5rem 1rem;">
                <div style="font-size:2.5rem;color:var(--sf-gold, #C6A15B);opacity:0.5;">
                    <i class="bi bi-geo-alt"></i>
                </div>
                <h2 style="font-family:'Playfair Display',serif;font-size:1.125rem;font-weight:500;margin:1rem 0 0.5rem;color:var(--sf-charcoal, #111111);">
                    No addresses yet
                </h2>
                <p style="color:var(--sf-muted, #777777);font-size:0.9375rem;margin:0 0 1.5rem;">
                    Add your first address to get started.
                </p>
                <a href="/account/addresses/create" class="sf-btn sf-btn-primary">
                    Add an address
                </a>
            </div>
        <?php else: ?>

            <div style="display:grid;gap:1rem;margin:1.5rem 0;">
                <?php foreach ($addresses as $a): ?>
                    <div style="border:1px solid rgba(198,161,91,0.25);padding:1.25rem;background:#ffffff;position:relative;">

                        <?php if ($a->isDefault): ?>
                            <span style="display:inline-block;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--sf-gold, #C6A15B);margin-bottom:0.5rem;">
                                Default
                            </span>
                        <?php endif; ?>

                        <?php if ($a->label !== null): ?>
                            <h3 style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:500;margin:0 0 0.5rem;color:var(--sf-charcoal, #111111);">
                                <?= e($a->label) ?>
                            </h3>
                        <?php endif; ?>

                        <div style="font-size:0.9375rem;color:var(--sf-charcoal, #111111);line-height:1.6;">
                            <?php if ($a->recipientName() !== ''): ?>
                                <div style="font-weight:500;"><?= e($a->recipientName()) ?></div>
                            <?php endif; ?>
                            <?php if ($a->company !== null): ?>
                                <div><?= e($a->company) ?></div>
                            <?php endif; ?>
                            <div><?= e($a->addressLine1) ?></div>
                            <?php if ($a->addressLine2 !== null): ?>
                                <div><?= e($a->addressLine2) ?></div>
                            <?php endif; ?>
                            <div>
                                <?= e($a->city) ?><?php if ($a->state !== null): ?>, <?= e($a->state) ?><?php endif; ?> <?= e((string) $a->postalCode) ?>
                            </div>
                            <div><?= e($a->countryCode) ?></div>
                            <?php if ($a->phone !== null): ?>
                                <div style="color:var(--sf-muted, #777777);margin-top:0.25rem;"><?= e($a->phone) ?></div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top:1rem;font-size:0.8125rem;color:var(--sf-muted, #777777);">
                            <?= $typeLabel($a->type) ?>
                        </div>

                        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:1rem;">
                            <a href="/account/addresses/<?= (int) $a->id ?>/edit"
                               class="sf-btn sf-btn-outline"
                               style="padding:0.375rem 0.875rem;font-size:0.8125rem;">
                                Edit
                            </a>

                            <?php if (!$a->isDefault): ?>
                                <form method="POST" action="/account/addresses/<?= (int) $a->id ?>/default" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="sf-btn sf-btn-outline" style="padding:0.375rem 0.875rem;font-size:0.8125rem;">
                                        Set as default
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" action="/account/addresses/<?= (int) $a->id ?>/delete" style="display:inline;"
                                  onsubmit="return confirm('Delete this address?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="sf-btn sf-btn-outline" style="padding:0.375rem 0.875rem;font-size:0.8125rem;color:#b91c1c;border-color:#fecaca;">
                                    Delete
                                </button>
                            </form>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:1.5rem;">
                <a href="/account/addresses/create" class="sf-btn sf-btn-primary">
                    Add another address
                </a>
            </div>

        <?php endif; ?>

        <p class="sf-auth-footer">
            <a href="/account">Back to account</a>
        </p>

    </div>
</div>
