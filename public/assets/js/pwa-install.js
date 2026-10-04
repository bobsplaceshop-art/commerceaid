/* ------------------------------------------------------------------
 * PWA install prompt.
 *
 * Chrome fires `beforeinstallprompt` when the app meets the
 * installability criteria (manifest parsed, service worker
 * registered, icons present). We intercept that event, prevent
 * the default browser mini-infobar, and show our own button in
 * the storefront header. The button is hidden by default.
 *
 * On click, we call prompt() on the saved event. Chrome shows
 * its native install dialog. If the user installs, we hide the
 * button forever (the appinstalled event fires). If they
 * dismiss, we hide the button for 30 days.
 *
 * The dismissal is stored in localStorage under
 * 'sf-pwa-install-dismissed-until' (a millisecond timestamp).
 * After that timestamp passes, the button becomes eligible
 * again the next time beforeinstallprompt fires.
 *
 * Silent on any failure. Installability is a nice-to-have,
 * not a requirement.
 * ------------------------------------------------------------------ */
(function () {
    'use strict';

    var DISMISS_KEY  = 'sf-pwa-install-dismissed-until';
    var DISMISS_DAYS = 30;
    var MS_PER_DAY   = 24 * 60 * 60 * 1000;

    var deferredEvent = null;
    var button        = null;

    /**
     * Read the dismissal timestamp from localStorage.
     * Returns 0 when the key is missing or the storage is
     * unavailable (privacy mode, quota errors).
     */
    function dismissedUntil() {
        try {
            var v = localStorage.getItem(DISMISS_KEY);
            if (!v) return 0;
            var n = parseInt(v, 10);
            return isNaN(n) ? 0 : n;
        } catch (e) {
            return 0;
        }
    }

    /**
     * Record a dismissal. The button stays hidden until the
     * timestamp passes.
     */
    function recordDismissal() {
        try {
            var until = Date.now() + (DISMISS_DAYS * MS_PER_DAY);
            localStorage.setItem(DISMISS_KEY, String(until));
        } catch (e) { /* ignore */ }
    }

    /**
     * Whether the button is currently eligible to show.
     */
    function isEligible() {
        return Date.now() >= dismissedUntil();
    }

    /**
     * Reveal the install button. Only called when
     * beforeinstallprompt fires and the dismissal window has
     * expired.
     */
    function showButton() {
        if (!button) return;
        button.hidden = false;
        button.setAttribute('aria-hidden', 'false');
    }

    /**
     * Hide the install button. Called on install or dismissal.
     */
    function hideButton() {
        if (!button) return;
        button.hidden = true;
        button.setAttribute('aria-hidden', 'true');
    }

    /**
     * Handle the button click. Shows Chrome's native install
     * dialog. Chrome resolves the promise with the user's
     * choice: 'accepted' or 'dismissed'.
     */
    function onClick() {
        if (!deferredEvent) return;

        deferredEvent.prompt();

        deferredEvent.userChoice.then(function (choice) {
            if (choice && choice.outcome === 'accepted') {
                // The appinstalled event will also fire; this
                // is just a fast path for hiding the button.
                hideButton();
            } else {
                recordDismissal();
                hideButton();
            }
            deferredEvent = null;
        }).catch(function () {
            // Some browsers do not resolve the promise. Fail
            // safe: hide the button and mark a dismissal.
            recordDismissal();
            hideButton();
            deferredEvent = null;
        });
    }

    /**
     * Wire everything up. Runs on DOMContentLoaded so the
     * button element is guaranteed to exist.
     */
    function boot() {
        button = document.querySelector('[data-sf-pwa-install]');
        if (!button) return;

        button.addEventListener('click', onClick);

        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferredEvent = e;
            if (isEligible()) {
                showButton();
            }
        });

        window.addEventListener('appinstalled', function () {
            hideButton();
            deferredEvent = null;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
