/* =====================================================================
 * Storefront product swatch selector.
 * =====================================================================
 * Loaded on product detail pages only.
 *
 * Replaces the old <select name="variant_id"> with a swatch
 * row: image swatches for color axes, text chips for size
 * and other axes.
 *
 * Markup contract:
 *   [data-sf-swatches]              root
 *     [data-sf-combinations]        JSON: "value_id:value_id" -> variant_id
 *     [data-sf-sellable]            comma-separated sellable variant ids
 *     [data-sf-variant-input]       hidden input to update
 *     [data-sf-axis]                one per variant axis
 *       [data-sf-swatch][data-axis][data-value-id][data-variant-ids]
 *       optional [data-swap-image]
 *
 * On click:
 *   1. Mark the clicked swatch active within its axis.
 *   2. Update the axis's selected value label.
 *   3. If every axis has a selection:
 *        - Build the combination key (value ids sorted asc, joined ':').
 *        - Look up the variant id.
 *        - If found and sellable, set the hidden input.
 *        - Else, mark the swatch disabled and clear the input.
 *   4. If the clicked swatch carries data-swap-image, swap the
 *      hero image (the [data-sf-gallery-main] <img>).
 *
 * Sellable filter:
 *   The container carries [data-sf-sellable] with a
 *   comma-separated list of sellable variant ids. A swatch
 *   whose every variant id is unsellable is rendered disabled.
 *   Partial availability (some in, some out) keeps the
 *   swatch clickable.
 * ===================================================================== */
(function () {
    'use strict';

    function initSwatches() {
        var root = document.querySelector('[data-sf-swatches]');
        if (!root) return;

        var input = root.querySelector('[data-sf-variant-input]');
        if (!input) return;

        // Parse the combinations map.
        var combinations = {};
        try {
            var raw = root.getAttribute('data-sf-combinations') || '{}';
            combinations = JSON.parse(raw) || {};
        } catch (e) {
            combinations = {};
        }

        // Parse the sellable variant ids.
        var sellable = {};
        var sellableRaw = root.getAttribute('data-sf-sellable') || '';
        sellableRaw.split(',').forEach(function (id) {
            var n = parseInt(id, 10);
            if (n > 0) sellable[n] = true;
        });

        // Group swatches by axis.
        var axes = {};
        root.querySelectorAll('[data-sf-swatch]').forEach(function (btn) {
            var axisSlug = btn.getAttribute('data-axis') || '';
            if (!axisSlug) return;
            axes[axisSlug] = axes[axisSlug] || [];
            axes[axisSlug].push(btn);
        });

        var selected = {};   // axis_slug -> value_id (int)

        // Disable swatches whose every variant id is unsellable.
        Object.keys(axes).forEach(function (slug) {
            axes[slug].forEach(function (btn) {
                var ids = (btn.getAttribute('data-variant-ids') || '').split(',');
                var anySellable = false;
                ids.forEach(function (id) {
                    var n = parseInt(id, 10);
                    if (n > 0 && sellable[n]) anySellable = true;
                });
                if (!anySellable) {
                    btn.classList.add('is-disabled');
                    btn.setAttribute('aria-disabled', 'true');
                    btn.disabled = true;
                }
            });
        });

        // Wire clicks.
        root.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-sf-swatch]');
            if (!btn || btn.disabled) return;
            e.preventDefault();

            var axisSlug = btn.getAttribute('data-axis') || '';
            var valueId = parseInt(btn.getAttribute('data-value-id'), 10);
            if (!axisSlug || valueId <= 0) return;

            // Toggle: clicking the active swatch clears the
            // selection for its axis.
            if (selected[axisSlug] === valueId) {
                selected[axisSlug] = 0;
            } else {
                selected[axisSlug] = valueId;
            }

            // Update the active state of every swatch in the axis.
            (axes[axisSlug] || []).forEach(function (sib) {
                var sibId = parseInt(sib.getAttribute('data-value-id'), 10);
                sib.classList.toggle('is-active', selected[axisSlug] === sibId);
            });

            // Swap the hero if the clicked swatch has an image.
            // Runs BEFORE the combination resolution because the
            // combination may be incomplete (Color selected, no
            // Size yet) and the swap is independent of the
            // combination.
            var swapUrl = btn.getAttribute('data-swap-image');
            if (swapUrl) {
                var main = document.querySelector('[data-sf-gallery-main]');
                if (main) main.src = swapUrl;
            }

            // Update the axis label.
            var axisEl = root.querySelector('[data-sf-axis="' + axisSlug + '"]');
            if (axisEl) {
                var labelEl = axisEl.querySelector('[data-sf-axis-value]');
                if (labelEl) {
                    labelEl.textContent = (selected[axisSlug] > 0)
                        ? btn.querySelector('.sf-swatch-label')?.textContent
                          || btn.getAttribute('title')
                          || ''
                        : '';
                }
            }

            // Resolve the combination if every axis has a selection.
            var allAxes = Object.keys(axes);
            var values = [];
            var complete = true;
            allAxes.forEach(function (slug) {
                var v = selected[slug] || 0;
                if (v <= 0) {
                    complete = false;
                } else {
                    values.push(v);
                }
            });

            if (!complete) {
                input.value = '';
                return;
            }

            values.sort(function (a, b) { return a - b; });
            var key = values.join(':');
            var variantId = combinations[key] || 0;

            if (variantId > 0 && sellable[variantId]) {
                input.value = String(variantId);
            } else {
                input.value = '';
            }

        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSwatches);
    } else {
        initSwatches();
    }
})();
