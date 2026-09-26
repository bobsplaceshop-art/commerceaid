<?php
/**
 * Product editor — SEO tab.
 */
$seoTitle = (string) $val('seoTitle');
$seoDesc  = (string) $val('seoDescription');
$name     = (string) $val('name');
?>
<div class="tab-pane fade<?= ($activeTab ?? 'basic') === 'seo' ? ' show active' : '' ?>" id="tab-seo" role="tabpanel">

    <div class="row g-3">
        <div class="col-12">
            <div class="muted small mb-2">
                These fields appear in search engine results and social shares. Leave blank to use defaults from the product name.
            </div>
        </div>

        <div class="col-12">
            <label for="seo_title" class="form-label">Page title</label>
            <input type="text" id="seo_title" name="seo_title" class="form-control"
                   maxlength="200"
                   value="<?= e($seoTitle) ?>"
                   placeholder="Defaults to product name">
            <div class="form-text">
                <span id="ra-seo-title-count"><?= mb_strlen($seoTitle) ?></span> / 200 characters.
                Search engines display the first ~60.
            </div>
            <?= $err('seo_title') ?>
        </div>

        <div class="col-12">
            <label for="seo_description" class="form-label">Meta description</label>
            <textarea id="seo_description" name="seo_description" class="form-control" rows="3"
                      maxlength="300"
                      placeholder="A compelling one-sentence summary"
                      ><?= e($seoDesc) ?></textarea>
            <div class="form-text">
                <span id="ra-seo-desc-count"><?= mb_strlen($seoDesc) ?></span> / 300 characters.
                Search engines display the first ~160.
            </div>
            <?= $err('seo_description') ?>
        </div>

        <div class="col-12">
            <label for="seo_keywords" class="form-label">Keywords</label>
            <input type="text" id="seo_keywords" name="seo_keywords" class="form-control"
                   maxlength="300"
                   value="<?= e((string) $val('seoKeywords')) ?>"
                   placeholder="Comma-separated (informational)">
        </div>

        <div class="col-12 mt-3">
            <label class="form-label">Search preview</label>
            <div class="ra-seo-preview">
                <div class="ra-seo-preview-title">
                    <?= e($seoTitle !== '' ? $seoTitle : ($name !== '' ? $name : 'Product name')) ?>
                </div>
                <div class="ra-seo-preview-url">
                    <?= e($_SERVER['HTTP_HOST'] ?? 'yoursite.com') ?>/products/<span id="ra-seo-preview-slug">slug</span>
                </div>
                <div class="ra-seo-preview-desc">
                    <?= e($seoDesc !== '' ? $seoDesc : 'A compelling meta description will appear here.') ?>
                </div>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label">Social image (OG image)</label>
            <div class="muted small">
                Image upload arrives with the media library. Uses the product's primary image until then.
            </div>
        </div>
    </div>

</div>

<style>
.ra-seo-preview {
    background: var(--ra-bg, #fff);
    border: 1px solid var(--ra-border, #e5e9f2);
    border-radius: 8px;
    padding: 1rem;
    max-width: 600px;
}
.ra-seo-preview-title {
    color: #1a0dab;
    font-size: 1.05rem;
    font-weight: 500;
    line-height: 1.3;
    margin-bottom: .15rem;
}
.ra-seo-preview-url {
    color: #006621;
    font-size: .8rem;
    margin-bottom: .25rem;
}
.ra-seo-preview-desc {
    color: #545454;
    font-size: .87rem;
    line-height: 1.4;
}
</style>

<script>
(function () {
    var titleInput = document.getElementById('seo_title');
    var descInput  = document.getElementById('seo_description');
    var titleCount = document.getElementById('ra-seo-title-count');
    var descCount  = document.getElementById('ra-seo-desc-count');
    var previewTitle = document.querySelector('.ra-seo-preview-title');
    var previewDesc  = document.querySelector('.ra-seo-preview-desc');

    function update() {
        if (titleCount) titleCount.textContent = (titleInput.value || '').length;
        if (descCount)  descCount.textContent  = (descInput.value  || '').length;
        if (previewTitle && titleInput.value) previewTitle.textContent = titleInput.value;
        if (previewDesc && descInput.value) previewDesc.textContent = descInput.value;
    }
    if (titleInput) titleInput.addEventListener('input', update);
    if (descInput)  descInput.addEventListener('input', update);
})();
</script>
