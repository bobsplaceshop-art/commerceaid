<?php
/**
 * TTDSG-compliant cookie consent banner.
 *
 * Rendered at the bottom of the storefront layout. The layout
 * only includes this partial when:
 *   - the compliance.cookie_banner.enabled setting is true
 *   - the visitor has no valid ca_consent cookie
 *
 * The banner is a fixed-position panel, not a modal. It does not
 * block the page. Two buttons of equal visual weight: Accept all
 * and Reject non-essential. No pre-checked anything.
 *
 * The consent decision is written to a ca_consent cookie by
 * cookie-consent.js and exposed at window.CAConsent.has(cat).
 * Stage 010 will read that to conditionally load analytics.
 *
 * See docs/stages/008e.1_german-compliance-plan.md.
 */
$store = $store ?? null;
?>
<div id="ca-cookie-banner"
     class="ca-cookie-banner"
     role="dialog"
     aria-modal="false"
     aria-labelledby="ca-cookie-title"
     hidden>

    <div class="ca-cookie-banner-inner">
        <div class="ca-cookie-banner-text">
            <h2 id="ca-cookie-title" class="ca-cookie-banner-title">
                We value your privacy
            </h2>
            <p class="ca-cookie-banner-body">
                We use essential cookies to run the store (cart, session,
                security). With your consent we would also like to use
                analytics and marketing cookies to improve your experience.
                You can accept all, or reject everything except what is
                strictly necessary.
            </p>
            <p class="ca-cookie-banner-links">
                <a href="/legal/datenschutz">Privacy policy</a>
                &middot;
                <a href="/legal/impressum">Impressum</a>
            </p>
        </div>

        <div class="ca-cookie-banner-actions">
            <button type="button"
                    class="ca-cookie-btn ca-cookie-btn-outline"
                    data-ca-consent="reject">
                Reject non-essential
            </button>
            <button type="button"
                    class="ca-cookie-btn ca-cookie-btn-primary"
                    data-ca-consent="accept">
                Accept all
            </button>
        </div>
    </div>
</div>
