<?php
/**
 * Legal page editor.
 *
 * Context (from LegalPageController::showEdit):
 *   $page  LegalPage
 *
 * The body uses a contenteditable rich text editor:
 *   - #legal-editor-toolbar  (buttons)
 *   - #legal-body-surface    (contenteditable div)
 *   - #legal-body-hidden     (textarea that is actually POSTed)
 *
 * JS in public/assets/js/legal-page-editor.js copies the stored
 * HTML into the surface on load, and copies the surface HTML back
 * into the hidden textarea on submit. The controller is unchanged
 * - it still receives $_POST['body'] as an HTML string.
 */
$page = $page ?? null;
if ($page === null) {
    return;
}

$slug        = (string) $page->slug;
$title       = (string) $page->title;
$body        = (string) $page->body;
$isPublished = $page->isPublished();
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Edit: <?= e($title) ?></h1>
        <p class="muted mb-0">
            <code class="small"><?= e($slug) ?></code>
            &middot; <a href="/legal/<?= e($slug) ?>" target="_blank" class="text-decoration-none">Preview on storefront</a>
        </p>
    </div>
</div>

<form method="POST"
      action="/commerce/ca-admin/legal-pages/<?= e($slug) ?>"
      id="ra-legal-edit-form">
    <?= csrf_field() ?>

    <div class="ra-card">
        <div class="ra-card-body">

            <div class="mb-3">
                <label for="legal-title" class="form-label">Title</label>
                <input type="text"
                       id="legal-title"
                       name="title"
                       class="form-control"
                       value="<?= e($title) ?>"
                       maxlength="190"
                       required>
                <div class="form-text">
                    Shown as the page heading and in the footer link.
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Body</label>

                <!-- Toolbar -->
                <div class="ra-editor-toolbar" id="legal-editor-toolbar" role="toolbar" aria-label="Formatting">
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

                <!-- Editor surface. Content is set from the hidden textarea by JS. -->
                <div id="legal-body-surface"
                     class="ra-editor-surface"
                     contenteditable="true"
                     spellcheck="true"
                     role="textbox"
                     aria-multiline="true"></div>

                <!-- The actual form field. JS syncs the surface into this on submit. -->
                <textarea id="legal-body-hidden"
                          name="body"
                          style="display:none;"><?= e($body) ?></textarea>

                <div class="form-text">
                    Edit the page as formatted text. Use the toolbar for
                    headings, bold, italic, and links. Pasting from Word
                    or Google Docs will strip extraneous formatting.
                </div>
            </div>

            <div class="form-check">
                <input type="checkbox"
                       id="legal-published"
                       name="is_published"
                       value="1"
                       class="form-check-input"
                       <?= $isPublished ? 'checked' : '' ?>>
                <label for="legal-published" class="form-check-label">
                    Published
                </label>
                <div class="form-text">
                    Unpublished pages are hidden from the storefront. If a
                    required page is unpublished, checkout will be blocked
                    until it is published again.
                </div>
            </div>

        </div>
    </div>

    <!-- Sticky action bar -->
    <div class="ra-sticky-actions">
        <div class="ra-sticky-actions-inner">
            <a href="/commerce/ca-admin/legal-pages" class="ra-btn ra-btn-outline">Cancel</a>
            <button type="submit" form="ra-legal-edit-form" class="ra-btn ra-btn-primary">
                <i class="bi bi-check-lg me-1"></i> Save page
            </button>
        </div>
    </div>

    <div class="ra-sticky-spacer"></div>
</form>

<script src="/assets/js/legal-page-editor.js"></script>

<style>
.ra-sticky-actions {
    position: fixed !important;
    bottom: 0 !important;
    left: var(--ra-sidebar-w, 260px) !important;
    right: 0 !important;
    padding: 1.5rem 2rem 1rem !important;
    background: linear-gradient(to top, #0b1220 55%, transparent 100%) !important;
    z-index: 900 !important;
    pointer-events: none !important;
}
[data-theme="light"] .ra-sticky-actions {
    background: linear-gradient(to top, #f4f6fb 55%, transparent 100%) !important;
}
.ra-sticky-actions-inner {
    display: flex !important;
    justify-content: flex-end !important;
    gap: .5rem !important;
    max-width: 1400px !important;
    margin: 0 auto !important;
    background: var(--ra-card-bg, #111c33) !important;
    border: 1px solid var(--ra-border, #1f2a44) !important;
    border-radius: 12px !important;
    padding: .75rem 1rem !important;
    box-shadow: 0 -4px 20px -8px rgba(0, 0, 0, 0.35) !important;
    pointer-events: auto !important;
}
.ra-sticky-spacer { height: 6rem !important; }
@media (max-width: 900px) {
    .ra-sticky-actions { left: 0 !important; padding: 1rem !important; }
}
</style>
