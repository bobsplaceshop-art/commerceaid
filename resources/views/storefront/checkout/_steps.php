<?php
/**
 * Checkout step indicator. Rendered at the top of every step.
 *
 * @var int $current  Current step number (1-5)
 */
$current = (int) ($current ?? 1);

$steps = [
    1 => 'Cart',
    2 => 'Details',
    3 => 'Shipping',
    4 => 'Payment',
    5 => 'Confirm',
];
?>
<nav class="sf-checkout-steps" aria-label="Checkout progress">
    <ol class="sf-checkout-steps-list">
        <?php foreach ($steps as $num => $label): ?>
            <?php
            $state = $num < $current ? 'is-complete'
                   : ($num === $current ? 'is-current' : 'is-upcoming');
            ?>
            <li class="sf-checkout-step <?= $state ?>">
                <span class="sf-checkout-step-num"><?= (int) $num ?></span>
                <span class="sf-checkout-step-label"><?= e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
