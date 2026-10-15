/**
 * Searchable country dropdown.
 *
 * Progressive enhancement for the country field on the
 * checkout shipping step and the account address book.
 *
 * The source of truth is a hidden <input> carrying the
 * two-letter code. The visible UI is a button that opens a
 * panel with a search field and a scrollable list of every
 * country from CountryCatalog. The customer types to filter,
 * clicks or presses Enter to select. The hidden input takes
 * the value; the visible button shows the flag and name.
 *
 * No dependency. No external request. The country data is
 * inlined into the DOM as a data attribute on the root
 * element, emitted by the PHP view from CountryCatalog.
 *
 * Usage:
 *
 *   <div class="sf-country-select"
 *        data-sf-country-select
 *        data-sf-country-value="DE">
 *     <input type="hidden"
 *            name="shipping_country_code"
 *            value="DE"
 *            data-sf-country-input>
 *     <button type="button"
 *             class="sf-country-select-toggle"
 *             data-sf-country-toggle
 *             aria-haspopup="listbox"
 *             aria-expanded="false">
 *         <span data-sf-country-display>Germany</span>
 *     </button>
 *     <div class="sf-country-select-panel"
 *          data-sf-country-panel
 *          role="listbox"
 *          hidden>
 *         <input type="text"
 *                class="sf-country-select-search"
 *                data-sf-country-search
 *                placeholder="Search countries..."
 *                autocomplete="off">
 *         <ul class="sf-country-select-list"
 *             data-sf-country-list></ul>
 *     </div>
 *   </div>
 *
 * The list items are generated from the JSON payload passed
 * in the root element's data-sf-country-options attribute.
 * That payload is the output of json_encode(CountryCatalog::all()).
 */
(function () {
    'use strict';

    function initSelect(root) {
        if (root.dataset.sfCountryReady === '1') return;
        root.dataset.sfCountryReady = '1';

        var input    = root.querySelector('[data-sf-country-input]');
        var toggle   = root.querySelector('[data-sf-country-toggle]');
        var display  = root.querySelector('[data-sf-country-display]');
        var panel    = root.querySelector('[data-sf-country-panel]');
        var search   = root.querySelector('[data-sf-country-search]');
        var listEl   = root.querySelector('[data-sf-country-list]');

        if (!input || !toggle || !panel || !search || !listEl) return;

        // Hide the text input — the button replaces it visually.
        // The input remains in the DOM and stays named, so it
        // posts the value on form submit. It is only visible if
        // this script fails to load.
        input.classList.add('sf-country-select-input-hidden');

        // Country data from the JSON attribute on the root.
        var countries = [];
        try {
            countries = JSON.parse(root.getAttribute('data-sf-country-options') || '[]');
        } catch (e) {
            countries = [];
        }
        if (!countries.length) return;

        // Render every row once. Filtering hides rows; it does
        // not re-render. The list is small (250 items) and the
        // rows are cheap.
        var rows = [];
        countries.forEach(function (c) {
            var li = document.createElement('li');
            li.className = 'sf-country-select-item';
            li.setAttribute('role', 'option');
            li.setAttribute('data-value', c.code);
            li.setAttribute('data-name', c.name.toLowerCase());
            li.setAttribute('tabindex', '-1');

            var flag = document.createElement('img');
            flag.className = 'sf-country-select-flag';
            flag.src = '/assets/flags/' + (c.code || '').toLowerCase() + '.svg';
            flag.alt = '';
            flag.width = 20;
            flag.height = 15;
            flag.loading = 'lazy';
            flag.onerror = function () { this.style.visibility = 'hidden'; };

            var name = document.createElement('span');
            name.className = 'sf-country-select-name';
            name.textContent = c.name;

            var code = document.createElement('span');
            code.className = 'sf-country-select-code';
            code.textContent = c.code;

            li.appendChild(flag);
            li.appendChild(name);
            li.appendChild(code);

            li.addEventListener('click', function () {
                select(c.code, c.name, c.flag);
            });

            listEl.appendChild(li);
            rows.push(li);
        });

        function findRow(code) {
            for (var i = 0; i < rows.length; i++) {
                if (rows[i].getAttribute('data-value') === code) return rows[i];
            }
            return null;
        }

        function highlight(row) {
            rows.forEach(function (r) {
                r.classList.remove('is-highlighted');
                r.setAttribute('aria-selected', 'false');
            });
            if (row) {
                row.classList.add('is-highlighted');
                row.setAttribute('aria-selected', 'true');
                // Scroll into view if off-screen.
                var listRect = listEl.getBoundingClientRect();
                var rowRect  = row.getBoundingClientRect();
                if (rowRect.top < listRect.top) {
                    listEl.scrollTop -= (listRect.top - rowRect.top);
                } else if (rowRect.bottom > listRect.bottom) {
                    listEl.scrollTop += (rowRect.bottom - listRect.bottom);
                }
            }
        }

        function open() {
            panel.removeAttribute('hidden');
            toggle.setAttribute('aria-expanded', 'true');
            var current = input.value;
            var currentRow = current ? findRow(current) : null;
            if (currentRow) {
                highlight(currentRow);
            }
            search.value = '';
            filter('');
            search.focus();
            document.addEventListener('keydown', onDocumentKey);
        }

        function close() {
            panel.setAttribute('hidden', '');
            toggle.setAttribute('aria-expanded', 'false');
            document.removeEventListener('keydown', onDocumentKey);
        }

        function isOpen() {
            return !panel.hasAttribute('hidden');
        }

        function select(code, name, flag) {
            input.value = code;
            input.dispatchEvent(new Event('change', { bubbles: true }));

            // Rebuild the toggle display: a flag img followed by
            // the country name. The flag comes from the row the
            // customer picked, so use that row's img directly.
            var pickedRow = findRow(code);
            var pickedImg = pickedRow ? pickedRow.querySelector('.sf-country-select-flag') : null;

            display.textContent = '';
            if (pickedImg) {
                var img = document.createElement('img');
                img.className = 'sf-country-select-flag';
                img.src = pickedImg.src;
                img.alt = '';
                img.width = 20;
                img.height = 15;
                display.appendChild(img);
                display.appendChild(document.createTextNode(' '));
            }
            display.appendChild(document.createTextNode(name));
            close();
            toggle.focus();
        }

        function filter(query) {
            var q = (query || '').trim().toLowerCase();
            var visible = 0;
            var firstVisible = null;
            rows.forEach(function (r) {
                var name = r.getAttribute('data-name');
                var code = r.getAttribute('data-value').toLowerCase();
                var show = q === '' || name.indexOf(q) !== -1 || code.indexOf(q) !== -1;
                if (show) {
                    r.removeAttribute('hidden');
                    visible++;
                    if (!firstVisible) firstVisible = r;
                } else {
                    r.setAttribute('hidden', '');
                }
            });
            highlight(firstVisible);
        }

        function onDocumentKey(e) {
            if (!isOpen()) return;

            if (e.key === 'Escape') {
                e.preventDefault();
                close();
                toggle.focus();
                return;
            }

            var highlighted = listEl.querySelector('.is-highlighted');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                var next = highlighted ? highlighted.nextElementSibling : rows[0];
                while (next && next.hasAttribute('hidden')) next = next.nextElementSibling;
                if (next) highlight(next);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                var prev = highlighted ? highlighted.previousElementSibling : rows[rows.length - 1];
                while (prev && prev.hasAttribute('hidden')) prev = prev.previousElementSibling;
                if (prev) highlight(prev);
            } else if (e.key === 'Enter') {
                if (highlighted) {
                    e.preventDefault();
                    highlighted.click();
                }
            }
        }

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (isOpen()) { close(); } else { open(); }
        });

        search.addEventListener('input', function () {
            filter(search.value);
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target) && isOpen()) {
                close();
            }
        });

        // If the hidden input already has a value on load (server
        // populated the customer's saved country), set the display
        // text. The initial display text from PHP is already
        // correct, so this is a no-op unless a JS-driven change
        // happened before init.
        // The PHP view has already set the display text on page
        // render. This is a no-op unless a JS-driven value change
        // happened before init. Left in place so a future async
        // update path works.
        if (input.value) {
            var row = findRow(input.value);
            if (row) {
                var n = row.querySelector('.sf-country-select-name');
                if (n && display.textContent === '') {
                    display.textContent = n.textContent;
                }
            }
        }
    }

    function initAll() {
        var roots = document.querySelectorAll('[data-sf-country-select]');
        for (var i = 0; i < roots.length; i++) {
            initSelect(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
