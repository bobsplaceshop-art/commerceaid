<?php
$store = $store ?? null;
$storeName = $store !== null ? $store->name : 'Store';
?>
<footer class="minimal-footer">
    &copy; <?= date('Y') ?> <?= e($storeName) ?>. All rights reserved.
    <div class="powered-by">
        Powered by <a href="https://corporateaidsolutions.co.ke" target="_blank" rel="noopener">Corporate Aid Solutions</a>
    </div>
</footer>
