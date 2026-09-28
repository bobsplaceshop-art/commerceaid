/**
 * Cookie consent — TTDSG framework.
 *
 * Two jobs:
 *   1. Show the banner when the visitor has no valid consent cookie.
 *   2. Expose window.CAConsent for code that needs to gate scripts
 *      on consent (Stage 010 analytics).
 *
 * Consent cookie: ca_consent. JSON payload with a version field.
 * Bumping CONSENT_VERSION invalidates all existing decisions and
 * the banner reappears for everyone.
 *
 * Public API:
 *   window.CAConsent.has(category)   -> boolean
 *   window.CAConsent.get()           -> full object
 *   window.CAConsent.reset()         -> clears the cookie (for testing)
 *   window.CAConsent.open()          -> shows the banner again
 */
(function () {
    'use strict';

    var COOKIE_NAME = 'ca_consent';
    var CONSENT_VERSION = 1;
    var COOKIE_MAX_AGE = 60 * 60 * 24 * 365; // 1 year

    // -----------------------------------------------------------------
    // Cookie helpers
    // -----------------------------------------------------------------

    function readConsentCookie() {
        var parts = document.cookie ? document.cookie.split('; ') : [];
        for (var i = 0; i < parts.length; i++) {
            var pair = parts[i].split('=');
            if (pair[0] === COOKIE_NAME) {
                try {
                    return JSON.parse(decodeURIComponent(pair.slice(1).join('=')));
                } catch (err) {
                    return null;
                }
            }
        }
        return null;
    }

    function writeConsentCookie(obj) {
        var payload = encodeURIComponent(JSON.stringify(obj));
        var attrs = '; path=/; SameSite=Lax; max-age=' + COOKIE_MAX_AGE;
        if (location.protocol === 'https:') {
            attrs += '; Secure';
        }
        document.cookie = COOKIE_NAME + '=' + payload + attrs;
    }

    function clearConsentCookie() {
        document.cookie = COOKIE_NAME + '=; path=/; max-age=0';
    }

    function hasValidConsent() {
        var c = readConsentCookie();
        return c !== null && c.version === CONSENT_VERSION;
    }

    // -----------------------------------------------------------------
    // Banner control
    // -----------------------------------------------------------------

    var banner = document.getElementById('ca-cookie-banner');

    function showBanner() {
        if (banner) {
            banner.hidden = false;
        }
    }

    function hideBanner() {
        if (banner) {
            banner.hidden = true;
        }
    }

    function applyDecision(analytics, marketing) {
        var consent = {
            version: CONSENT_VERSION,
            essential: true,
            analytics: !!analytics,
            marketing: !!marketing,
            timestamp: Math.floor(Date.now() / 1000)
        };
        writeConsentCookie(consent);
        hideBanner();
        // Notify any listeners (future analytics init).
        try {
            window.dispatchEvent(new CustomEvent('ca:consent', { detail: consent }));
        } catch (err) {
            // Very old browsers. Ignore.
        }
    }

    // -----------------------------------------------------------------
    // Wire buttons
    // -----------------------------------------------------------------

    if (banner) {
        banner.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ca-consent]');
            if (!btn) {
                return;
            }
            var action = btn.getAttribute('data-ca-consent');
            if (action === 'accept') {
                applyDecision(true, true);
            } else if (action === 'reject') {
                applyDecision(false, false);
            }
        });
    }

    // -----------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------

    window.CAConsent = {
        has: function (category) {
            var c = readConsentCookie();
            if (!c || c.version !== CONSENT_VERSION) {
                return false;
            }
            if (category === 'essential') {
                return true;
            }
            return !!c[category];
        },
        get: function () {
            return readConsentCookie();
        },
        reset: function () {
            clearConsentCookie();
            if (banner) {
                showBanner();
            }
        },
        open: function () {
            if (banner) {
                showBanner();
            }
        }
    };

    // -----------------------------------------------------------------
    // Init
    // -----------------------------------------------------------------

    function init() {
        if (!banner) {
            return; // banner not rendered (setting off or already consented)
        }
        if (!hasValidConsent()) {
            showBanner();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
