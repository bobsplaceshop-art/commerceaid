<?php
/**
 * Compose or edit a campaign. Stage 008I.
 *
 * Context:
 *   $mode     'create' | 'edit'
 *   $campaign ?EmailCampaign
 *   $lists    array<int, SubscriberList>
 *   $errors   array<string,string>
 *   $old      array<string,mixed>
 */
$mode     = $mode     ?? 'create';
$campaign = $campaign ?? null;
$lists    = $lists    ?? [];
$errors   = $errors   ?? [];
$old      = $old      ?? [];

$isEdit = $mode === 'edit' && $campaign !== null;
$action = $isEdit
    ? '/commerce/ca-admin/campaigns/' . (int) $campaign->id
    : '/commerce/ca-admin/campaigns';

$val = function (string $key, string $default = '') use ($old, $campaign): string {
    if (array_key_exists($key, $old)) return (string) $old[$key];
    if ($campaign === null) return $default;
    return match ($key) {
        'subject'   => (string) $campaign->subject,
        'preheader' => (string) ($campaign->preheader ?? ''),
        'body_html' => (string) $campaign->bodyHtml,
        'body_text' => (string) ($campaign->bodyText ?? ''),
        'list_id'   => (string) $campaign->listId,
        default     => $default,
    };
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};

$currentListId = (int) $val('list_id', '0');
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/campaigns" class="text-decoration-none">Campaigns</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= $isEdit ? 'Edit' : 'New campaign' ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= $isEdit ? e($campaign->subject) : 'New campaign' ?></h1>
        <?php if ($isEdit): ?>
            <p class="muted mb-0">
                <span class="ra-badge ra-badge-muted"><?= e($campaign->statusLabel()) ?></span>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="<?= e($action) ?>" id="ra-campaign-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Header</h2>
            <div class="row g-3">

                <div class="col-md-8">
                    <label class="muted small" for="camp-subject">
                        Subject <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="camp-subject" name="subject"
                           class="form-control <?= $err('subject') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('subject')) ?>"
                           maxlength="255"
                           placeholder="This month's new arrivals"
                           required>
                    <?php if ($err('subject') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('subject')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="muted small" for="camp-list">
                        Subscriber list <span aria-hidden="true">*</span>
                    </label>
                    <select id="camp-list" name="list_id"
                            class="form-select <?= $err('list_id') !== '' ? 'is-invalid' : '' ?>"
                            required
                            <?= $isEdit && !$campaign->canEdit() ? 'disabled' : '' ?>>
                        <option value="">— Select a list —</option>
                        <?php foreach ($lists as $l): ?>
                            <option value="<?= (int) $l->id ?>"
                                    <?= $currentListId === (int) $l->id ? 'selected' : '' ?>>
                                <?= e($l->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($err('list_id') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('list_id')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="muted small" for="camp-preheader">Preheader</label>
                    <input type="text" id="camp-preheader" name="preheader"
                           class="form-control <?= $err('preheader') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('preheader')) ?>"
                           maxlength="255"
                           placeholder="Short preview text shown next to the subject in most inboxes">
                    <?php if ($err('preheader') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('preheader')) ?></div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Body</h2>

            <div class="ra-editor-toolbar" id="campaign-editor-toolbar" role="toolbar" aria-label="Formatting">
                <button type="button" class="ra-editor-btn" data-cmd="bold" title="Bold"><b>B</b></button>
                <button type="button" class="ra-editor-btn" data-cmd="italic" title="Italic"><i>I</i></button>
                <span class="ra-editor-sep" aria-hidden="true"></span>
                <button type="button" class="ra-editor-btn" data-cmd="formatBlock" data-value="h2" title="Heading 2">H2</button>
                <button type="button" class="ra-editor-btn" data-cmd="formatBlock" data-value="h3" title="Heading 3">H3</button>
                <button type="button" class="ra-editor-btn" data-cmd="formatBlock" data-value="p" title="Paragraph">&para;</button>
                <span class="ra-editor-sep" aria-hidden="true"></span>
                <button type="button" class="ra-editor-btn" data-cmd="insertUnorderedList" title="Bullet list">&bull;</button>
                <button type="button" class="ra-editor-btn" data-cmd="insertOrderedList" title="Numbered list">1.</button>
                <span class="ra-editor-sep" aria-hidden="true"></span>
                <button type="button" class="ra-editor-btn" data-cmd="createLink" title="Insert link">&#128279;</button>
                <button type="button" class="ra-editor-btn" data-cmd="removeFormat" title="Clear formatting">&#10005;</button>
            </div>

            <div id="campaign-body-surface"
                 class="ra-editor-surface"
                 contenteditable="true"
                 spellcheck="true"></div>
            <textarea id="campaign-body-hidden"
                      name="body_html"
                      style="display:none;"
                      required><?= e($val('body_html')) ?></textarea>

            <?php if ($err('body_html') !== ''): ?>
                <div class="invalid-feedback d-block mt-2"><?= e($err('body_html')) ?></div>
            <?php endif; ?>

            <div class="muted small mt-2">
                The email wraps this body in the standard store header and
                footer, including the mandatory unsubscribe link.
            </div>
        </div>
    </div>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/campaigns" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-campaign-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check2 me-1"></i> <?= $isEdit ? 'Save draft' : 'Save draft' ?>
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>

<script src="/assets/js/legal-page-editor.js"></script>
<script>
(function () {
    'use strict';
    if (window.RA && window.RA.initRichEditor) {
        window.RA.initRichEditor({
            surface: 'campaign-body-surface',
            hidden:  'campaign-body-hidden',
            toolbar: 'campaign-editor-toolbar',
            form:    'ra-campaign-form'
        });
    }
})();
</script>
