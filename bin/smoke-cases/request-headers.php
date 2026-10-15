<?php
declare(strict_types=1);

/**
 * Smoke case: Request::header() case-insensitive lookup.
 *
 * The 2026-10-15 PayPal webhook session found that
 * Request::header() did an exact string match. The
 * producer (Request::extractHeaders) title-cases header
 * names from $_SERVER. So HTTP_PAYPAL_TRANSMISSION_ID
 * became 'Paypal-Transmission-Id' in $this->headers. A
 * caller that asked for 'PayPal-Transmission-Id' (the
 * brand capitalisation) got null. The PayPal webhook
 * provider silently bailed at a guard, and every real
 * delivery logged 'webhook.signature-invalid'.
 *
 * The fix added a case-insensitive fallback. This case
 * is the guard against a future regression.
 *
 * The case builds a synthetic $_SERVER that mimics what
 * Apache passes to PHP for a real webhook, calls
 * Request::capture(), and asserts that header() reads
 * the value under any capitalisation the caller asks
 * for.
 *
 * No database. No network. Pure PHP.
 *
 * Returns true on pass, a string describing the failure
 * on fail. The sibling of refund.php and
 * stripe-webhook.php; discovered by glob, run by
 * composer smoke.
 */

use CorporateAid\Core\Request;

return function ($container, PDO $pdo): bool|string {

    $failures = [];

    // Preserve the current superglobals so the case is
    // self-contained and does not pollute the harness.
    $savedServer = $_SERVER;

    try {
        // ---------------------------------------------------------
        // Synthetic $_SERVER, shaped exactly as Apache would
        // deliver a PayPal webhook through mod_php. Header
        // names are uppercased and underscore-separated, per
        // the HTTP_* convention.
        // ---------------------------------------------------------
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/webhooks/paypal';
        $_SERVER['HTTP_HOST']      = 'localhost';
        $_SERVER['HTTP_USER_AGENT'] = 'smoke/1.0';

        $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID']   = 'tx-id-abc';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] = '2026-10-15T18:00:00Z';
        $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG']  = 'sig-xyz';
        $_SERVER['HTTP_PAYPAL_CERT_URL']          = 'https://example.com/cert';
        $_SERVER['HTTP_PAYPAL_AUTH_ALGO']         = 'SHA256withRSA';

        // ---------------------------------------------------------
        // Build the Request the way the router does.
        // ---------------------------------------------------------
        $request = Request::capture();

        // ---------------------------------------------------------
        // Assertion 1: the producer's exact output case.
        // Trace extractHeaders('HTTP_PAYPAL_TRANSMISSION_ID'):
        //   substr(_, 5)                -> PAYPAL_TRANSMISSION_ID
        //   str_replace('_', ' ')       -> PAYPAL TRANSMISSION ID
        //   strtolower                  -> paypal transmission id
        //   ucwords                     -> Paypal Transmission Id
        //   str_replace(' ', '-')       -> Paypal-Transmission-Id
        // The fast path through array_key_exists hits here.
        // ---------------------------------------------------------
        $viaProducer = $request->header('Paypal-Transmission-Id');
        if ($viaProducer !== 'tx-id-abc') {
            $failures[] = "assertion 1 (producer case): got "
                . var_export($viaProducer, true) . ", expected 'tx-id-abc'";
        }

        // ---------------------------------------------------------
        // Assertion 2: the consumer's brand capitalisation.
        // This is the exact case the pre-fix code failed.
        // PayPal's own brand spells it with a capital P in
        // both syllables; extractHeaders produces Paypal.
        // ---------------------------------------------------------
        $viaBrand = $request->header('PayPal-Transmission-Id');
        if ($viaBrand !== 'tx-id-abc') {
            $failures[] = "assertion 2 (brand case): got "
                . var_export($viaBrand, true) . ", expected 'tx-id-abc'";
        }

        // ---------------------------------------------------------
        // Assertion 3: the all-caps form.
        // ---------------------------------------------------------
        $viaCaps = $request->header('PAYPAL-TRANSMISSION-ID');
        if ($viaCaps !== 'tx-id-abc') {
            $failures[] = "assertion 3 (all caps): got "
                . var_export($viaCaps, true) . ", expected 'tx-id-abc'";
        }

        // ---------------------------------------------------------
        // Assertion 4: the all-lowercase form.
        // ---------------------------------------------------------
        $viaLower = $request->header('paypal-transmission-id');
        if ($viaLower !== 'tx-id-abc') {
            $failures[] = "assertion 4 (all lower): got "
                . var_export($viaLower, true) . ", expected 'tx-id-abc'";
        }

        // ---------------------------------------------------------
        // Assertion 5: a missing header returns the caller's
        // default. The fallback loop must not fabricate a
        // match against a string that shares a prefix.
        // ---------------------------------------------------------
        $missing = $request->header('X-Does-Not-Exist', 'fallback');
        if ($missing !== 'fallback') {
            $failures[] = "assertion 5 (missing): got "
                . var_export($missing, true) . ", expected 'fallback'";
        }

        // ---------------------------------------------------------
        // Assertion 6: the other four PayPal headers also
        // read back through the case-insensitive path. Same
        // shape as assertion 2, different field names.
        // ---------------------------------------------------------
        $checks = [
            'PayPal-Transmission-Time' => '2026-10-15T18:00:00Z',
            'PayPal-Transmission-Sig'  => 'sig-xyz',
            'PayPal-Cert-Url'          => 'https://example.com/cert',
            'PayPal-Auth-Algo'         => 'SHA256withRSA',
        ];
        foreach ($checks as $name => $expected) {
            $actual = $request->header($name);
            if ($actual !== $expected) {
                $failures[] = "assertion 6 ({$name}): got "
                    . var_export($actual, true) . ", expected "
                    . var_export($expected, true);
            }
        }

        // ---------------------------------------------------------
        // Assertion 7: a non-PayPal header still reads
        // through the fast path. HTTP_HOST becomes Host.
        // ---------------------------------------------------------
        $host = $request->header('Host');
        if ($host !== 'localhost') {
            $failures[] = "assertion 7 (Host fast path): got "
                . var_export($host, true)
                . ", expected 'localhost'";
        }

        // ---------------------------------------------------------
        // Assertion 8: the fallback does not confuse a
        // key with a longer neighbour. X-Paypal is a
        // prefix of X-Paypal-Transmission-Id; asking for
        // X-Paypal must not match the longer key.
        // ---------------------------------------------------------
        $_SERVER['HTTP_X_PAYPAL_TRANSMISSION_ID'] = 'should-not-match';
        // Re-capture so the new server state is reflected.
        $request2 = Request::capture();
        $notMatched = $request2->header('X-Paypal', 'no-match');
        if ($notMatched !== 'no-match') {
            $failures[] = "assertion 8 (prefix ambiguity): got "
                . var_export($notMatched, true) . ", expected 'no-match'";
        }
        unset($_SERVER['HTTP_X_PAYPAL_TRANSMISSION_ID']);

    } finally {
        // Restore the superglobals no matter what.
        $_SERVER = $savedServer;
    }

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
