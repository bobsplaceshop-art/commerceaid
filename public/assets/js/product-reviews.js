/* ------------------------------------------------------------------
 * Load more reviews.
 *
 * The Reviews tab renders the first page of approved reviews
 * server-side. When there are more, the partial renders a
 * "Load more reviews" button with data-slug, data-offset,
 * and data-total. This script fetches the next page from
 * GET /products/{slug}/reviews?offset=N and appends the
 * returned items to the list.
 *
 * The button removes itself when has_more is false.
 *
 * No dependencies. Silent on failure: the button re-enables
 * and shows an error state.
 * ---------------------------------------------------------------- */
(function () {
    'use strict';

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderReview(r) {
        var stars = '';
        var rating = parseInt(r.rating, 10) || 0;
        for (var i = 1; i <= 5; i++) {
            stars += '<i class="bi ' +
                (i <= rating ? 'bi-star-fill' : 'bi-star') +
                '" aria-hidden="true"></i>';
        }
        var verified = r.verified_purchase
            ? '<span class="sf-review-verified"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Verified purchase</span>'
            : '';
        var title = r.title
            ? '<h4 class="sf-review-title">' + escapeHtml(r.title) + '</h4>'
            : '';
        var initial = (r.author_name || 'A').charAt(0).toUpperCase();
        var author = r.is_anonymous ? 'Anonymous' : (r.author_name || 'Anonymous');

        return (
            '<li class="sf-review-item">' +
                '<header class="sf-review-header">' +
                    '<div class="sf-review-author">' +
                        '<span class="sf-review-avatar" aria-hidden="true">' + escapeHtml(initial) + '</span>' +
                        '<span class="sf-review-meta">' +
                            '<span class="sf-review-author-name">' + escapeHtml(author) + '</span>' +
                            verified +
                        '</span>' +
                    '</div>' +
                    '<div class="sf-review-date muted">' + escapeHtml(r.created_at || '') + '</div>' +
                '</header>' +
                '<div class="sf-review-stars">' + stars + '</div>' +
                title +
                '<div class="sf-review-body">' + escapeHtml(r.body || '').replace(/\n/g, '<br>') + '</div>' +
            '</li>'
        );
    }

    function boot() {
        var btn = document.getElementById('sf-reviews-load-more');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var slug = btn.getAttribute('data-slug') || '';
            var offset = parseInt(btn.getAttribute('data-offset'), 10) || 0;
            if (slug === '') return;

            btn.disabled = true;
            var original = btn.textContent;
            btn.textContent = 'Loading\u2026';

            fetch('/products/' + encodeURIComponent(slug) + '/reviews?offset=' + offset, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var list = document.getElementById('sf-reviews-list');
                    var items = data.reviews || [];
                    items.forEach(function (r) {
                        list.insertAdjacentHTML('beforeend', renderReview(r));
                    });
                    var newOffset = offset + items.length;
                    btn.setAttribute('data-offset', newOffset);

                    if (!data.has_more) {
                        var wrap = btn.parentNode;
                        if (wrap && wrap.parentNode) {
                            wrap.parentNode.removeChild(wrap);
                        }
                    } else {
                        btn.disabled = false;
                        btn.textContent = original;
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = 'Could not load more. Try again.';
                    setTimeout(function () {
                        btn.textContent = original;
                    }, 2500);
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
