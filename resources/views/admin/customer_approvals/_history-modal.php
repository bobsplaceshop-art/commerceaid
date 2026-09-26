<?php
/**
 * Approval history modal.
 *
 * Opened from index.php via:
 *   onclick='RA_ApprovalHistory.open(id, name)'
 *
 * Fetches the timeline from:
 *   GET /commerce/ca-admin/customer-approvals/{id}/history
 *
 * Renders a vertical timeline of state transitions, newest first.
 * Each entry shows:
 *   - Badge (success/danger/warning/muted) with the target status
 *   - Action label ("Account created", "Approved", etc.)
 *   - Actor display name (or "System")
 *   - Timestamp
 *   - Reason if present (admin-only text)
 *
 * Closes via:
 *   - X button
 *   - Close button
 *   - Backdrop click
 *   - Escape key
 */
?>

<div id="ra-history-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-history-close></div>
    <div class="ra-modal-panel" role="dialog" aria-modal="true" aria-labelledby="ra-history-modal-title">
        <div class="ra-modal-header">
            <h2 class="h6 mb-0" id="ra-history-modal-title">Approval history</h2>
            <button type="button" class="ra-icon-btn" aria-label="Close" data-ra-history-close>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="ra-modal-body">
            <div class="mb-3">
                <div><strong id="ra-history-customer-name">Customer</strong></div>
                <div class="muted small" id="ra-history-customer-email">-</div>
            </div>

            <div id="ra-history-content">
                <div class="muted small text-center py-3">Loading history...</div>
            </div>
        </div>

        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" data-ra-history-close>
                Close
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal      = document.getElementById('ra-history-modal');
    var contentEl  = document.getElementById('ra-history-content');
    var nameEl     = document.getElementById('ra-history-customer-name');
    var emailEl    = document.getElementById('ra-history-customer-email');

    if (!modal || !contentEl) return;

    var HISTORY_URL_TEMPLATE = '/commerce/ca-admin/customer-approvals/__ID__/history';

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function badgeClass(variant) {
        switch (variant) {
            case 'success': return 'ra-badge ra-badge-success';
            case 'danger':  return 'ra-badge ra-badge-danger';
            case 'warning': return 'ra-badge ra-badge-warning';
            default:        return 'ra-badge';
        }
    }

    function renderEntry(entry) {
        var reason = '';
        if (entry.reason && entry.reason.length > 0) {
            reason = '<div class="muted small mt-1" style="border-left:2px solid var(--ra-muted,#94a3b8);padding-left:.5rem;">'
                   + escapeHtml(entry.reason)
                   + '</div>';
        }

        var statusChange = '';
        if (entry.from_status && entry.from_status !== entry.to_status) {
            statusChange = '<div class="muted" style="font-size:.72rem;">'
                         + escapeHtml(entry.from_status) + ' &rarr; ' + escapeHtml(entry.to_status)
                         + '</div>';
        }

        return ''
            + '<div style="padding:.6rem 0;border-bottom:1px solid rgba(148,163,184,0.15);">'
            +   '<div class="d-flex justify-content-between align-items-start gap-3">'
            +     '<div class="flex-grow-1" style="min-width:0;">'
            +       '<div class="d-flex align-items-center gap-2 flex-wrap">'
            +         '<span class="' + badgeClass(entry.badge_variant) + '" style="font-size:.7rem;">'
            +           escapeHtml(entry.action)
            +         '</span>'
            +         statusChange
            +       '</div>'
            +       '<div class="muted small mt-1">by ' + escapeHtml(entry.actor_name) + '</div>'
            +       reason
            +     '</div>'
            +     '<div class="muted small text-end" style="white-space:nowrap;">'
            +       escapeHtml(entry.created_at_display)
            +     '</div>'
            +   '</div>'
            + '</div>';
    }

    function renderTimeline(history) {
        if (!history || history.length === 0) {
            return '<div class="muted small text-center py-3">No history recorded yet.</div>';
        }
        var html = '';
        for (var i = 0; i < history.length; i++) {
            html += renderEntry(history[i]);
        }
        return html;
    }

    function open(id, name) {
        nameEl.textContent  = name || 'Customer';
        emailEl.textContent = '';
        contentEl.innerHTML = '<div class="muted small text-center py-3">Loading history...</div>';

        modal.style.display = '';
        document.body.style.overflow = 'hidden';

        var url = HISTORY_URL_TEMPLATE.replace('__ID__', String(id));

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                if (data.error) {
                    contentEl.innerHTML = '<div class="ra-alert ra-alert-error">' + escapeHtml(data.error) + '</div>';
                    return;
                }
                if (data.customer) {
                    nameEl.textContent  = data.customer.name || 'Customer';
                    emailEl.textContent = data.customer.email || '';
                }
                contentEl.innerHTML = renderTimeline(data.history);
            })
            .catch(function (err) {
                contentEl.innerHTML = '<div class="ra-alert ra-alert-error">Could not load history: ' + escapeHtml(err.message) + '</div>';
            });
    }

    function close() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    modal.querySelectorAll('[data-ra-history-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            close();
        }
    });

    window.RA_ApprovalHistory = {
        open: open,
        close: close
    };
})();
</script>
