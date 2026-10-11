/**
 * Legal page editor.
 *
 * A minimal contenteditable rich-text editor. Zero dependencies.
 * Uses document.execCommand (deprecated but functional in every
 * shipping browser). The merchant sees formatted text, not HTML
 * source. On submit, the contenteditable div's HTML is copied
 * into a hidden textarea so the form posts it normally.
 *
 * Features:
 *   - Bold, italic, headings, paragraph, link, lists
 *   - Paste sanitizer: pasted content is stripped to plain
 *     text + basic block tags. No Word/Docs junk.
 *   - Sticky toolbar
 *
 * Not a full editor. No images, no tables, no undo history UI.
 * For legal pages that change once a quarter, that is correct.
 */
(function () {
    'use strict';

    // Default element IDs for the legal page editor.
    // Other views (e.g. the newsletter campaign composer)
    // call window.RA.initRichEditor({...}) with their own
    // IDs. The wiring is identical.
    var DEFAULT_IDS = {
        surface: 'legal-body-surface',
        hidden:  'legal-body-hidden',
        toolbar: 'legal-editor-toolbar',
        form:    'ra-legal-edit-form'
    };

    function initEditor(ids) {
        ids = ids || DEFAULT_IDS;
        var surface = document.getElementById(ids.surface);
        var hidden  = document.getElementById(ids.hidden);
        var toolbar = document.getElementById(ids.toolbar);
        var form    = document.getElementById(ids.form);

        if (!surface || !hidden || !toolbar || !form) {
            return;
        }

        // -------------------------------------------------------
        // Seed content
        // -------------------------------------------------------
        // The hidden textarea holds the stored HTML. Copy it into
        // the contenteditable surface on init. Do NOT re-encode;
        // innerHTML is the right sink.
        var initial = hidden.value;
        if (initial && initial.trim() !== '') {
            surface.innerHTML = initial;
        }

        // -------------------------------------------------------
        // Sync on submit
        // -------------------------------------------------------
        form.addEventListener('submit', function () {
            hidden.value = surface.innerHTML;
        });

        // Also sync on any blur to be safe (browser back, autosave
        // future, etc.). Cheap.
        surface.addEventListener('blur', function () {
            hidden.value = surface.innerHTML;
        });

        // -------------------------------------------------------
        // Toolbar buttons
        // -------------------------------------------------------
        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-cmd]');
            if (!btn) {
                return;
            }
            e.preventDefault();

            var cmd   = btn.getAttribute('data-cmd');
            var value = btn.getAttribute('data-value') || null;

            surface.focus();

            if (cmd === 'createLink') {
                var url = prompt('Link URL:', 'https://');
                if (!url) {
                    return;
                }
                document.execCommand('createLink', false, url);
            } else if (cmd === 'formatBlock' && value) {
                document.execCommand('formatBlock', false, value);
            } else {
                document.execCommand(cmd, false, value);
            }

            updateActiveStates();
            hidden.value = surface.innerHTML;
        });

        // Prevent toolbar mousedown from stealing focus from the
        // contenteditable. Otherwise the selection is lost before
        // execCommand runs.
        toolbar.addEventListener('mousedown', function (e) {
            e.preventDefault();
        });

        // -------------------------------------------------------
        // Active button highlighting (best effort)
        // -------------------------------------------------------
        function updateActiveStates() {
            toolbar.querySelectorAll('[data-cmd]').forEach(function (b) {
                var cmd = b.getAttribute('data-cmd');
                if (cmd === 'formatBlock' || cmd === 'createLink') {
                    return;
                }
                try {
                    if (document.queryCommandState(cmd)) {
                        b.classList.add('is-active');
                    } else {
                        b.classList.remove('is-active');
                    }
                } catch (err) {
                    // Some browsers throw on certain queryCommandState calls.
                }
            });
        }

        surface.addEventListener('keyup', updateActiveStates);
        surface.addEventListener('mouseup', updateActiveStates);

        // -------------------------------------------------------
        // Paste sanitizer
        // -------------------------------------------------------
        // Strip all formatting on paste. Insert plain text.
        // Merchants pasting from Word or Google Docs get clean
        // content, no calibri / 11pt / span soup.
        surface.addEventListener('paste', function (e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text/plain');
            if (!text) {
                return;
            }
            // insertText respects the current block context, so
            // pasting into an H2 keeps the H2 tag on the pasted
            // content. Good enough for legal pages.
            document.execCommand('insertText', false, text);
            hidden.value = surface.innerHTML;
        });
    }

    // Public API. Views that need a rich-text editor with
    // different element IDs call window.RA.initRichEditor()
    // explicitly. The default behavior for the legal page
    // editor is preserved: on DOMContentLoaded, if the
    // legal IDs exist, they are wired.
    window.RA = window.RA || {};
    window.RA.initRichEditor = initEditor;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initEditor();
        });
    } else {
        initEditor();
    }
})();
