<?php
declare(strict_types=1);

/**
 * Smoke case: Request::capture() preserves header names
 * against case-insensitive lookup.
 *
 * Guards the bug class fixed in 863c950 (PayPal webhook
 * session, 2026-10-15). Before that fix, Request::header()
 * did an exact string match against $this->headers, which
 * is built by extractHeaders() with title-cased names.
 * A caller asking for "PayPal-Transmission-Id" against a
 * producer that emitted "Paypal-Transmission-Id" got null.
 * The webhook signature check failed with a message that
 * named the wrong cause (invalid signature, not a key-case
 * mismatch). The bug cost an hour to diagnose.
 *
 * The fix added a case-insensitive fallback in header().
 * This case makes sure the fallback stays. It exercises
 * the whole pipeline: a synthetic $_SERVER with a
 * brand-capitalised key, Request::capture() reading it,
 * and header() returning the value for any case variant
 * the caller may ask for.
 *
 * The case does not touch the database. It does not need
 * the container. It swaps $_SERVER, captures, restores,
 * and asserts.
 *
 * The setup restores $_SERVER in a finally block so a
 * later case in the same process sees the real server
 * array. The harness runs every case in one PHP process.
 *
 * Returns true on pass, a string on failure.
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo  the test database connection (unused)
 */

use CorporateAid\Core\Request;

return function ($container, PDO $pdo): bool|string {

    $failures = [];

    // --------------------------------------------------------------
    // Fixture: a $_SERVER with the exact producer shape the
    // bug report names. A real PayPal webhook arrives with
    // these HTTP_* entries. PHP's superglobals preserve the
    // raw underscore form. extractHeaders() converts each
    // one to a title-cased name.
    //
    //   HTTP_PAYPAL_TRANSMISSION_ID -> Paypal-Transmission-Id
    //   HTTP_STRIPE_SIGNATURE       -> Stripe-Signature
    //   HTTP_X_CUSTOM_BRAND_HEADER  -> X-Custom-Brand-Header
    //
    // Both brands that touch this project (PayPal, Stripe)
    // appear in the fixture. The third is a synthetic
    // hyphenated name that exercises the extractHeaders()
    // transformation beyond the payment providers.
    // --------------------------------------------------------------
    $syntheticServer = [
        'REQUEST_METHOD'                  => 'POST',
        'REQUEST_URI'                     => '/webhooks/paypal',
        'REMOTE_ADDR'                     => '127.0.0.1',
        'HTTP_HOST'                       => 'localhost:8084',
        'HTTP_PAYPAL_TRANSMISSION_ID'     => 'transmission-abc-123',
        'HTTP_PAYPAL_TRANSMISSION_TIME'   => '2026-10-17T10:00:00Z',
        'HTTP_PAYPAL_TRANSMISSION_SIG'    => 'sig-xyz-789',
        'HTTP_PAYPAL_AUTH_ALGO'           => 'SHA256withRSA',
        'HTTP_PAYPAL_CERT_URL'            => 'https://api.paypal.com/cert.pem',
        'HTTP_STRIPE_SIGNATURE'           => 't=1700000000,v1=abc123',
        'HTTP_X_CUSTOM_BRAND_HEADER'      => 'custom-value',
    ];

    // Swap $_SERVER. Save the original so a finally block
    // can restore it. The harness is a single PHP process;
    // a case that corrupts $_SERVER affects every later
    // case.
    $originalServer = $_SERVER;

    try {
        $_SERVER = $syntheticServer;

        $request = Request::capture();

        // ----------------------------------------------------------
        // Assertion 1: header() returns the value for the exact
        // producer form. The producer (extractHeaders) title-
        // cases each header, so the internal key is
        // "Paypal-Transmission-Id". A caller asking for that
        // exact string gets the value.
        // ----------------------------------------------------------
        $exact = $request->header('Paypal-Transmission-Id');
        if ($exact !== 'transmission-abc-123') {
            $failures[] = 'assertion 1: exact form "Paypal-Transmission-Id" '
                . 'returned ' . var_export($exact, true)
                . ', expected "transmission-abc-123"';
        }

        // ----------------------------------------------------------
        // Assertion 2: header() returns the value for the
        // caller's brand-capitalisation. This is the bug the
        // fallback fixes. "PayPal-Transmission-Id" (with a
        // capital P in each syllable) is not the producer's
        // internal key but is the same HTTP header.
        // ----------------------------------------------------------
        $branded = $request->header('PayPal-Transmission-Id');
        if ($branded !== 'transmission-abc-123') {
            $failures[] = 'assertion 2: brand-capitalised form "PayPal-Transmission-Id" '
                . 'returned ' . var_export($branded, true)
                . ', expected "transmission-abc-123" (case-insensitive fallback broken)';
        }

        // ----------------------------------------------------------
        // Assertion 3: an all-lowercase query works too. A
        // future caller that normalises header names to
        // lowercase still reaches the value.
        // ----------------------------------------------------------
        $lower = $request->header('paypal-transmission-id');
        if ($lower !== 'transmission-abc-123') {
            $failures[] = 'assertion 3: lowercase form "paypal-transmission-id" '
                . 'returned ' . var_export($lower, true)
                . ', expected "transmission-abc-123"';
        }

        // ----------------------------------------------------------
        // Assertion 4: a Stripe header also works. The same
        // extractHeaders() transformation applies, and the
        // same fallback catches a Stripe-shaped query.
        // ----------------------------------------------------------
        $stripe = $request->header('Stripe-Signature');
        if ($stripe !== 't=1700000000,v1=abc123') {
            $failures[] = 'assertion 4: "Stripe-Signature" '
                . 'returned ' . var_export($stripe, true)
                . ', expected "t=1700000000,v1=abc123"';
        }

        // ----------------------------------------------------------
        // Assertion 5: an unknown header returns the caller's
        // default. The fallback must not accidentally match
        // unrelated headers.
        // ----------------------------------------------------------
        $missing = $request->header('X-Does-Not-Exist', 'default-value');
        if ($missing !== 'default-value') {
            $failures[] = 'assertion 5: unknown header returned '
                . var_export($missing, true)
                . ', expected "default-value"';
        }

        // ----------------------------------------------------------
        // Assertion 6: an unknown header without a default
        // returns null. Matches the method's contract.
        // ----------------------------------------------------------
        $nullMissing = $request->header('X-Does-Not-Exist');
        if ($nullMissing !== null) {
            $failures[] = 'assertion 6: unknown header without default returned '
                . var_export($nullMissing, true)
                . ', expected null';
        }

        // ----------------------------------------------------------
        // Assertion 7: the extractHeaders transformation itself
        // is exercised. The internal key for the custom header
        // is "X-Custom-Brand-Header". A caller asking for that
        // exact form gets the value. This guards the
        // transformation against a future refactor that
        // changes the case handling.
        // ----------------------------------------------------------
        $custom = $request->header('X-Custom-Brand-Header');
        if ($custom !== 'custom-value') {
            $failures[] = 'assertion 7: "X-Custom-Brand-Header" '
                . 'returned ' . var_export($custom, true)
                . ', expected "custom-value"';
        }

        // ----------------------------------------------------------
        // Assertion 8: the fallback works for a case variant
        // of the custom header. "x-custom-brand-header" (all
        // lowercase) is the caller's possible form.
        // ----------------------------------------------------------
        $customLower = $request->header('x-custom-brand-header');
        if ($customLower !== 'custom-value') {
            $failures[] = 'assertion 8: lowercase custom header returned '
                . var_export($customLower, true)
                . ', expected "custom-value"';
        }

    } finally {
        // Restore the real $_SERVER. Any case that runs after
        // this one needs the original superglobal state.
        $_SERVER = $originalServer;
    }

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
