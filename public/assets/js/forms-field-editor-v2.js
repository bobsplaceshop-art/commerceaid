/**
 * Forms field editor. Stage 009 Session 2b.
 *
 * Uses the shared admin modal system (.ra-modal*).
 * Visibility is controlled by inline style.display, matching
 * every other admin modal (attribute delete, brand delete,
 * coupon delete, inventory adjust, and so on).
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        var modal     = document.getElementById('ra-field-modal');
        var form      = document.getElementById('ra-field-form');
        var title     = document.getElementById('ra-field-modal-title');
        var typeSel   = document.querySelector('[data-ra-field-type]');
        var optionsSection = document.querySelector('[data-ra-field-options-section]');
        var optionsList    = document.querySelector('[data-ra-field-options-list]');
        var optionTemplate = document.getElementById('ra-field-option-template');
        var configsWrapper = document.querySelector('[data-ra-field-type-configs]');
        var condField      = document.querySelector('[data-ra-field-cond-field]');

        if (!modal || !form || !typeSel) return;

        var formsBase = form.getAttribute('action');

        // -----------------------------------------------------------------
        // Type → config section and options visibility
        // -----------------------------------------------------------------

        function updateTypeSections() {
            var current = typeSel.value;

            document.querySelectorAll('[data-ra-field-type-section]').forEach(function (el) {
                el.hidden = el.getAttribute('data-ra-field-type-section') !== current;
            });

            var anyVisible = false;
            document.querySelectorAll('[data-ra-field-type-section]').forEach(function (el) {
                if (!el.hidden) anyVisible = true;
            });
            if (configsWrapper) configsWrapper.hidden = !anyVisible;

            var supportsOptions = typeSel.options[typeSel.selectedIndex]
                .getAttribute('data-supports-options') === '1';
            if (optionsSection) optionsSection.hidden = !supportsOptions;
        }

        typeSel.addEventListener('change', updateTypeSections);

        // -----------------------------------------------------------------
        // Conditional field select — hide current field on edit
        // -----------------------------------------------------------------

        function hideConditionalOption(fieldId) {
            if (!condField) return;
            Array.prototype.forEach.call(condField.options, function (opt) {
                var optFieldId = opt.getAttribute('data-field-id');
                if (optFieldId && optFieldId === String(fieldId)) {
                    opt.disabled = true;
                    opt.hidden = true;
                }
            });
            // If the currently selected option is now hidden, reset the select.
            if (condField.selectedOptions[0] && condField.selectedOptions[0].disabled) {
                condField.value = '';
            }
        }

        function resetConditionalOptions() {
            if (!condField) return;
            Array.prototype.forEach.call(condField.options, function (opt) {
                opt.disabled = false;
                opt.hidden = false;
            });
        }

        // -----------------------------------------------------------------
        // Options editor
        // -----------------------------------------------------------------

        function addOptionRow(value, label) {
            if (!optionTemplate || !optionsList) return;
            var clone = optionTemplate.content.cloneNode(true);
            var inputs = clone.querySelectorAll('input');
            if (inputs[0]) inputs[0].value = value || '';
            if (inputs[1]) inputs[1].value = label || '';

            var removeBtn = clone.querySelector('[data-ra-field-option-remove]');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    var row = removeBtn.closest('[data-ra-field-option-row]');
                    if (row) row.remove();
                });
            }

            optionsList.appendChild(clone);
        }

        var addOptBtn = document.querySelector('[data-ra-field-option-add]');
        if (addOptBtn) {
            addOptBtn.addEventListener('click', function () {
                addOptionRow('', '');
            });
        }

        // -----------------------------------------------------------------
        // Open / close
        // -----------------------------------------------------------------

        function clearForm() {
            form.reset();
            form.setAttribute('action', formsBase);
            if (optionsList) optionsList.innerHTML = '';
            if (title) title.textContent = 'Add field';
            resetConditionalOptions();
            updateTypeSections();
        }

        function openAdd() {
            clearForm();
            show();
        }

        function openEdit(btn) {
            clearForm();
            if (title) title.textContent = 'Edit field';

            var fieldId = btn.getAttribute('data-field-id');
            form.setAttribute('action', formsBase + '/' + fieldId);

            var set = function (name, value) {
                var el = form.querySelector('[name="' + name + '"]');
                if (el) el.value = value || '';
            };
            var check = function (name, value) {
                var el = form.querySelector('[name="' + name + '"]');
                if (el) el.checked = value === '1';
            };

            set('field_key', btn.getAttribute('data-field-key'));
            set('label', btn.getAttribute('data-field-label'));
            typeSel.value = btn.getAttribute('data-field-type') || 'text';
            set('placeholder', btn.getAttribute('data-field-placeholder'));
            set('help_text', btn.getAttribute('data-field-help'));
            set('step', btn.getAttribute('data-field-step'));
            set('default_value', btn.getAttribute('data-field-default'));
            check('is_required', btn.getAttribute('data-field-required'));

            var optionsRaw = btn.getAttribute('data-field-options');
            if (optionsRaw) {
                try {
                    var options = JSON.parse(optionsRaw);
                    if (Array.isArray(options)) {
                        options.forEach(function (opt) {
                            addOptionRow(opt.value || '', opt.label || '');
                        });
                    }
                } catch (e) { /* malformed */ }
            }

            var constraintsRaw = btn.getAttribute('data-field-constraints');
            if (constraintsRaw) {
                try {
                    var constraints = JSON.parse(constraintsRaw);
                    if (constraints && typeof constraints === 'object') {
                        Object.keys(constraints).forEach(function (k) {
                            var el = form.querySelector('[name="constraints[' + k + ']"]');
                            if (el) el.value = constraints[k];
                        });
                    }
                } catch (e) { /* malformed */ }
            }

            var conditionalRaw = btn.getAttribute('data-field-conditional');
            if (conditionalRaw) {
                try {
                    var cond = JSON.parse(conditionalRaw);
                    if (cond && typeof cond === 'object') {
                        set('conditional[field]', cond.field || '');
                        set('conditional[operator]', cond.operator || '');
                        set('conditional[value]', cond.value || '');
                    }
                } catch (e) { /* malformed */ }
            }

            // The current field cannot conditionally depend on itself.
            hideConditionalOption(fieldId);

            updateTypeSections();
            show();
        }

        function show() {
            modal.style.display = 'flex';
        }

        function close() {
            modal.style.display = 'none';
        }

        // -----------------------------------------------------------------
        // Bindings
        // -----------------------------------------------------------------

        document.querySelectorAll('[data-ra-field-add]').forEach(function (btn) {
            btn.addEventListener('click', openAdd);
        });

        document.querySelectorAll('[data-ra-field-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () { openEdit(btn); });
        });

        document.querySelectorAll('[data-ra-field-close]').forEach(function (btn) {
            btn.addEventListener('click', close);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') close();
        });

        updateTypeSections();
    });
})();
