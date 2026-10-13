/* =====================================================================
 * Storefront forms — conditional logic and multi-step navigation.
 * =====================================================================
 *
 * Stage 009 Session 3. Loaded globally with defer; no-ops on
 * pages without a form.
 *
 * Two behaviors:
 *
 *   1. Conditional field visibility. Every field wrapper that
 *      carries a `data-sf-conditional` attribute is shown or
 *      hidden based on the current value of another field.
 *      The rule shape is JSON:
 *        {"field":"other_key","operator":"equals","value":"yes"}
 *
 *      The server pre-renders the initial visible state based on
 *      the last submitted values, so a page reload after a
 *      validation failure shows the same fields the customer
 *      saw before. This script takes over from that point: on
 *      every input or change event inside the form, each rule
 *      is re-evaluated and the wrapper's `hidden` attribute is
 *      set accordingly.
 *
 *   2. Multi-step navigation. A form with more than one
 *      `.sf-form-step` element renders one step at a time.
 *      Next advances, Back retreats, and the indicator's
 *      "Step N of M" updates. The whole form submits at once;
 *      the step structure is presentation only. The server
 *      validates the whole form as a single unit.
 *
 * Markup contract (from resources/views/storefront/forms/show.php):
 *
 *   [data-sf-form-form]            the <form> element
 *   [data-sf-field]                a field wrapper
 *     [data-sf-conditional='...']  optional JSON rule on the wrapper
 *   [data-sf-step="N"]             a step wrapper
 *   [data-sf-step-indicator]       the "Step N of M" text holder
 *   [data-sf-step-current]         the current number inside it
 *   [data-sf-step-prev]            the Back button
 *   [data-sf-step-next]            the Next button
 *   [data-sf-step-submit]          the submit button
 *
 * Field value reading: the CustomFields InputType classes emit
 * inputs named `cf[field_key]` (single value) or
 * `cf[field_key][]` (multi-value). The reader below tries both.
 * ===================================================================== */

(function () {
    'use strict';

    // -----------------------------------------------------------------
    // Field value reader.
    // -----------------------------------------------------------------
    //
    // Returns the current submitted-shape string for a field key.
    // Tries the single-value name first, then the multi-value
    // name. Multi-value inputs (checkbox groups, multi-select)
    // join their checked values with commas, matching the
    // storage convention documented in FormSubmission::fromRow().

    function readFieldValue(form, fieldKey) {
        var single = form.querySelector(
            '[name="cf[' + cssEscape(fieldKey) + ']"]'
        );
        if (single) {
            return (single.value || '').toString().trim();
        }

        var multi = form.querySelectorAll(
            '[name="cf[' + cssEscape(fieldKey) + '][]"]'
        );
        if (multi.length) {
            var values = [];
            multi.forEach(function (el) {
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (el.checked) values.push(el.value);
                } else {
                    values.push(el.value || '');
                }
            });
            return values.join(',');
        }

        return '';
    }

    // -----------------------------------------------------------------
    // CSS attribute-value escaping.
    // -----------------------------------------------------------------
    //
    // Field keys are stored lowercase with underscores, but a
    // defensive escape keeps the selector safe if a key ever
    // contains a bracket or a quote.

    function cssEscape(value) {
        return String(value).replace(/(["\\\]\[])/g, '\\$1');
    }

    // -----------------------------------------------------------------
    // Rule evaluation.
    // -----------------------------------------------------------------
    //
    // Mirrors FormSubmissionService::isFieldVisible(). Both sides
    // must agree on what "visible" means; a mismatch would let the
    // client show a field the server then validates, or hide a
    // field the server still expects.

    function evaluateRule(rule, actual) {
        if (!rule || typeof rule !== 'object') return true;

        var operator = String(rule.operator || '');
        var expected = rule.value === undefined || rule.value === null
            ? ''
            : String(rule.value);
        actual = String(actual || '');

        switch (operator) {
            case 'equals':        return actual === expected;
            case 'not_equals':    return actual !== expected;
            case 'contains':      return actual.indexOf(expected) !== -1;
            case 'is_empty':      return actual === '';
            case 'is_not_empty':  return actual !== '';
            case 'greater_than':
                return isFinite(parseFloat(actual))
                    && isFinite(parseFloat(expected))
                    && parseFloat(actual) > parseFloat(expected);
            case 'less_than':
                return isFinite(parseFloat(actual))
                    && isFinite(parseFloat(expected))
                    && parseFloat(actual) < parseFloat(expected);
            default:
                return true;
        }
    }

    // -----------------------------------------------------------------
    // Form initialization.
    // -----------------------------------------------------------------

    function initForm(form) {
        var fieldWrappers = form.querySelectorAll('[data-sf-field]');

        // Cache the conditional rule per wrapper. Parsing JSON
        // on every input event would be wasteful; the rule is
        // immutable for the page's lifetime.
        var rules = [];
        fieldWrappers.forEach(function (wrapper) {
            var raw = wrapper.getAttribute('data-sf-conditional');
            if (!raw) return;

            var parsed;
            try {
                parsed = JSON.parse(raw);
            } catch (e) {
                return;
            }
            if (!parsed || typeof parsed.field !== 'string') return;
            rules.push({ wrapper: wrapper, rule: parsed });
        });

        // Re-evaluate every rule against the current form state.
        // Applied on load (idempotent — the server pre-rendered
        // the same state) and on every input or change event.
        function reapplyConditionals() {
            rules.forEach(function (entry) {
                var actual = readFieldValue(form, entry.rule.field);
                var visible = evaluateRule(entry.rule, actual);
                if (visible) {
                    entry.wrapper.removeAttribute('hidden');
                } else {
                    entry.wrapper.setAttribute('hidden', '');
                }
            });
        }

        if (rules.length) {
            form.addEventListener('input', reapplyConditionals);
            form.addEventListener('change', reapplyConditionals);
            reapplyConditionals();
        }

        // -----------------------------------------------------------------
        // Multi-step navigation.
        // -----------------------------------------------------------------

        var steps = Array.prototype.slice.call(
            form.querySelectorAll('[data-sf-step]')
        );
        if (steps.length < 2) return;

        var stepNumbers = steps.map(function (el) {
            return parseInt(el.getAttribute('data-sf-step'), 10) || 0;
        });

        var current = 0;

        var indicator = form.querySelector('[data-sf-step-current]');
        var btnPrev = form.querySelector('[data-sf-step-prev]');
        var btnNext = form.querySelector('[data-sf-step-next]');
        var btnSubmit = form.querySelector('[data-sf-step-submit]');

        function render() {
            steps.forEach(function (el, i) {
                if (i === current) {
                    el.removeAttribute('hidden');
                } else {
                    el.setAttribute('hidden', '');
                }
            });

            if (indicator) {
                indicator.textContent = String(stepNumbers[current] || (current + 1));
            }

            if (btnPrev) {
                if (current === 0) btnPrev.setAttribute('hidden', '');
                else btnPrev.removeAttribute('hidden');
            }

            if (btnNext) {
                if (current >= steps.length - 1) btnNext.setAttribute('hidden', '');
                else btnNext.removeAttribute('hidden');
            }

            if (btnSubmit) {
                if (current >= steps.length - 1) btnSubmit.removeAttribute('hidden');
                else btnSubmit.setAttribute('hidden', '');
            }
        }

        if (btnNext) {
            btnNext.addEventListener('click', function () {
                if (current < steps.length - 1) {
                    current++;
                    render();
                    // Scroll the form's top into view so the
                    // customer sees the new step's fields.
                    var top = form.getBoundingClientRect().top + window.pageYOffset - 80;
                    window.scrollTo({ top: top, behavior: 'smooth' });
                }
            });
        }

        if (btnPrev) {
            btnPrev.addEventListener('click', function () {
                if (current > 0) {
                    current--;
                    render();
                    var top = form.getBoundingClientRect().top + window.pageYOffset - 80;
                    window.scrollTo({ top: top, behavior: 'smooth' });
                }
            });
        }

        render();
    }

    // -----------------------------------------------------------------
    // Entry point.
    // -----------------------------------------------------------------

    function boot() {
        document.querySelectorAll('[data-sf-form-form]').forEach(initForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
