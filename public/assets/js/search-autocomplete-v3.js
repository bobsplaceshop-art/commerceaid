/**
 * Header search autocomplete — premium.
 *
 * Debounces input on .sf-header-search-input, fetches
 * /search/suggest?q=..., renders a dropdown of product cards
 * (thumbnail, name, price) plus a "View all results" footer.
 *
 * Keyboard: Up/Down/Enter/Escape.
 * Closes on click outside.
 */
(function () {
    'use strict';

    var input = document.querySelector('.sf-header-search-input');
    if (!input) return;

    var wrapper = input.closest('.sf-header-search');
    if (!wrapper) return;

    wrapper.style.position = 'relative';

    // Inject the stylesheet once. Uses the storefront CSS
    // variables (--sf-*) so it follows the theme.
    if (!document.getElementById('sf-suggest-styles')) {
        var style = document.createElement('style');
        style.id = 'sf-suggest-styles';
        style.textContent = [
            '.sf-suggest {',
            '  position: absolute; top: calc(100% + 6px); left: 0; right: 0;',
            '  background: #fff;',
            '  border: 1px solid rgba(0,0,0,0.08);',
            '  border-radius: 12px;',
            '  box-shadow: 0 12px 40px rgba(15,23,42,0.12);',
            '  z-index: 1000;',
            '  max-height: 460px; overflow-y: auto;',
            '  padding: 6px;',
            '  font-family: var(--sf-font-body, system-ui, sans-serif);',
            '}',
            '.sf-suggest-item {',
            '  display: flex; align-items: center; gap: 12px;',
            '  padding: 8px 10px;',
            '  border-radius: 8px;',
            '  cursor: pointer;',
            '  transition: background .12s ease;',
            '  text-decoration: none; color: inherit;',
            '}',
            '.sf-suggest-item:hover, .sf-suggest-item.is-active {',
            '  background: #f6f2ea;',
            '}',
            '.sf-suggest-thumb {',
            '  width: 44px; height: 44px; flex-shrink: 0;',
            '  border-radius: 6px; border: 1px solid rgba(0,0,0,0.06);',
            '  background: #f4f4f4; object-fit: cover;',
            '  display: flex; align-items: center; justify-content: center;',
            '  color: #b0a89a; font-size: 18px;',
            '}',
            '.sf-suggest-body { flex: 1; min-width: 0; }',
            '.sf-suggest-label {',
            '  font-size: .9rem; font-weight: 500; color: #1a1a1a;',
            '  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;',
            '}',
            '.sf-suggest-price {',
            '  font-size: .85rem; font-weight: 600; color: #b8942e;',
            '  white-space: nowrap; margin-left: auto;',
            '}',
            '.sf-suggest-footer {',
            '  display: flex; align-items: center; justify-content: space-between;',
            '  padding: 10px 14px; margin-top: 4px;',
            '  border-top: 1px solid rgba(0,0,0,0.06);',
            '  font-size: .82rem; color: #6b6b6b;',
            '  cursor: pointer; text-decoration: none;',
            '}',
            '.sf-suggest-footer:hover { color: #1a1a1a; }',
            '.sf-suggest-empty {',
            '  padding: 14px; text-align: center; font-size: .85rem; color: #8a8a8a;',
            '}'
        ].join('\n');
        document.head.appendChild(style);
    }

    var dropdown = document.createElement('div');
    dropdown.className = 'sf-suggest';
    dropdown.style.display = 'none';
    wrapper.appendChild(dropdown);

    var timer = null;
    var activeIndex = -1;
    var suggestions = [];
    var query = '';

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function formatPrice(n) {
        return '$' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function clear() {
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        activeIndex = -1;
        suggestions = [];
    }

    function highlight(index) {
        var items = dropdown.querySelectorAll('.sf-suggest-item');
        items.forEach(function (el, i) {
            el.classList.toggle('is-active', i === index);
        });
        activeIndex = index;
        if (items[index]) { items[index].scrollIntoView({ block: 'nearest' }); }
    }

    function render(list, total, q) {
        suggestions = list;
        query = q;

        if (list.length === 0) {
            dropdown.innerHTML = '<div class="sf-suggest-empty">No products match "' + escapeHtml(q) + '"</div>';
            dropdown.style.display = 'block';
            return;
        }

        var html = '';
        list.forEach(function (item, i) {
            var thumb = item.image_url
                ? '<img src="' + escapeHtml(item.image_url) + '" alt="" class="sf-suggest-thumb">'
                : '<span class="sf-suggest-thumb"><i class="bi bi-box-seam"></i></span>';
            html += '<a class="sf-suggest-item" href="' + escapeHtml(item.url) + '" data-index="' + i + '">'
                  + thumb
                  + '<div class="sf-suggest-body"><div class="sf-suggest-label">' + escapeHtml(item.label) + '</div></div>'
                  + '<span class="sf-suggest-price">' + formatPrice(item.price) + '</span>'
                  + '</a>';
        });
        html += '<a class="sf-suggest-footer" href="/search?q=' + encodeURIComponent(q) + '">'
              + '<span>View all ' + Number(total) + ' results for "' + escapeHtml(q) + '"</span>'
              + '<span>→</span>'
              + '</a>';
        dropdown.innerHTML = html;
        dropdown.style.display = 'block';
        activeIndex = -1;
    }

    function fetchSuggestions(q) {
        if (q.length < 2) { clear(); return; }
        fetch('/search/suggest?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && Array.isArray(data.suggestions)) {
                    render(data.suggestions, data.total || 0, data.query || q);
                }
            })
            .catch(function () { clear(); });
    }

    input.addEventListener('input', function () {
        var q = this.value.trim();
        clearTimeout(timer);
        timer = setTimeout(function () { fetchSuggestions(q); }, 250);
    });

    input.addEventListener('keydown', function (e) {
        var items = dropdown.querySelectorAll('.sf-suggest-item');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlight(Math.min(activeIndex + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlight(Math.max(activeIndex - 1, 0));
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            window.location.href = suggestions[activeIndex].url;
        } else if (e.key === 'Escape') {
            clear();
        }
    });

    document.addEventListener('click', function (e) {
        if (!wrapper.contains(e.target)) { clear(); }
    });
})();
