<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

/**
 * Guards against open-redirect attacks.
 *
 * The attacker-controlled input is any URL that a legitimate flow
 * might use as a post-authentication destination: ?intended=,
 * ?next=, ?redirect=, ?return_to=. These reach a redirect() call
 * somewhere downstream. If the value is a fully-qualified external
 * URL, the application becomes an open redirect and can be used in
 * phishing.
 *
 * The rule: only paths that begin with a single forward slash and
 * contain no scheme, no protocol-relative prefix, no backslash
 * trick, and no newline are safe to redirect to.
 *
 * Used by:
 *   - Commerce\Controllers\AuthController (admin login)
 *   - Commerce\Controllers\Storefront\AuthController (customer auth)
 *   - Future licensing flows (activation, reauthorization,
 *     migration, transfer)
 *
 * The licensing architecture has multiple redirect-after-action
 * flows. Every one of them validates the same way. One class,
 * one implementation, one place to fix if the rule ever changes.
 */
final class RedirectGuard
{
    /**
     * True if the given URL is safe to redirect to within the
     * current application. False for anything that could leave the
     * host or smuggle a scheme.
     *
     * Rules:
     *   - Must not be empty
     *   - Must start with '/'
     *   - Must not start with '//' (protocol-relative)
     *   - Must not start with '/\' (backslash trick)
     *   - Must not contain newline or carriage return
     */
    public static function isSafeInternalRedirect(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        if ($url[0] !== '/') {
            return false;
        }
        if (str_starts_with($url, '//')) {
            return false;
        }
        if (str_starts_with($url, '/\\')) {
            return false;
        }
        if (str_contains($url, "\n") || str_contains($url, "\r")) {
            return false;
        }
        return true;
    }
}
