<?php
/** @var array<string,string> $errors */
/** @var array<string,mixed> $old */
$errors = $errors ?? [];
$old    = $old ?? [];
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">New page</h1>
        <p class="muted mb-0">Create a page for your storefront.</p>
    </div>
    <a href="/commerce/ca-admin/pages" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to Pages
    </a>
</div>

<form method="POST" action="/commerce/ca-admin/pages" id="ra-page-create-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-header"><h2 class="h6 mb-0">Page details</h2></div>
        <div class="ra-card-body">
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" value="<?= e($old['title'] ?? '') ?>" placeholder="About Us">
                <?php if (!empty($errors['title'])): ?>
                    <div class="small text-danger mt-1"><?= e($errors['title']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">URL slug</label>
                <input type="text" name="slug" class="form-control" value="<?= e($old['slug'] ?? '') ?>" placeholder="about-us">
                <div class="form-text">Lowercase letters, numbers, and hyphens. The page is served at <code>/pages/{slug}</code>.</div>
                <?php if (!empty($errors['slug'])): ?>
                    <div class="small text-danger mt-1"><?= e($errors['slug']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="/commerce/ca-admin/pages" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" class="ra-btn ra-btn-primary">Create page</button>
    </div>
</form>

<script>
(function () {
    var titleInput = document.querySelector('input[name="title"]');
    var slugInput  = document.querySelector('input[name="slug"]');
    if (!titleInput || !slugInput) return;

    // If the slug is already filled (from old input after a failed
    // submit), treat it as manually edited. Do not overwrite.
    var slugManuallyEdited = slugInput.value.trim() !== '';

    slugInput.addEventListener('input', function () {
        slugManuallyEdited = true;
    });

    titleInput.addEventListener('input', function () {
        if (slugManuallyEdited) return;

        var slug = titleInput.value
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')   // strip accents
            .replace(/[^a-z0-9]+/g, '-')         // non-alphanumeric → hyphen
            .replace(/^-+|-+$/g, '');            // trim leading/trailing hyphens

        slugInput.value = slug;
    });

    // Clear stale validation errors as soon as the user types.
    document.querySelectorAll('input[type="text"]').forEach(function (input) {
        input.addEventListener('input', function () {
            var field = this.parentElement;
            if (!field) return;
            var error = field.querySelector('.text-danger');
            if (error) error.style.display = 'none';
        });
    });
})();
</script>
