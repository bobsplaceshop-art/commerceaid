<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Exceptions\NewsletterException;
use CorporateAid\Commerce\Services\SubscriberService;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Newsletter subscribe, confirm, and unsubscribe.
 *
 * Public routes. No authentication. The subscribe endpoint
 * is rate-limited at the route level (per IP) and inside
 * SubscriberService (per email address), so the double
 * opt-in flow cannot be turned into a subscription bomb.
 *
 * Routes:
 *   POST /newsletter/subscribe
 *   GET  /newsletter/confirm/{token}
 *   GET  /newsletter/unsubscribe/{token}
 *   POST /newsletter/unsubscribe/{token}
 *
 * The raw tokens are HMAC-signed by SignedToken and carry
 * the subscriber id and a scope ('confirm' or
 * 'unsubscribe'). Every failure renders the same neutral
 * message so an attacker cannot tell whether a token was
 * malformed, expired, forged, or refers to a nonexistent
 * subscriber.
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class NewsletterController extends StorefrontController
{
    /**
     * POST /newsletter/subscribe
     *
     * The form posts from the footer or the home page. Both
     * forms carry a hidden 'source' field identifying which
     * one it was. An empty 'website' field is required (the
     * honeypot); bots fill every input they see.
     *
     * The response is identical for every outcome: new
     * subscribe, re-subscribe, already confirmed, refused
     * (unsubscribed/suppressed), honeypot tripped, or
     * invalid input. The message never reveals state.
     */
    /** @param array<string,string> $params */
    public function subscribe(Request $request, array $params = []): Response
    {
        // Honeypot. A bot that fills every input trips this
        // and receives the generic success response. No email
        // is sent and no row is written.
        $honeypot = trim((string) $request->input('website', ''));
        if ($honeypot !== '') {
            return $this->subscribeRedirect();
        }

        $email   = trim((string) $request->input('email', ''));
        $name    = trim((string) $request->input('name', ''));
        $source  = (string) $request->input('source', 'footer');
        if ($source !== 'home' && $source !== 'footer') {
            $source = 'footer';
        }

        $service = $this->container->get(SubscriberService::class);

        try {
            $service->subscribe(
                $email,
                $name !== '' ? $name : null,
                [
                    'source'     => $source,
                    'ip'         => $request->ip(),
                    'user_agent' => (string) $request->header('User-Agent', ''),
                ],
                null
            );
        } catch (NewsletterException $e) {
            // Invalid email, missing list, or similar. The
            // exception message is safe to surface. It never
            // discloses whether an address is already on the
            // list.
            $this->flash('error', $e->getMessage());
            return $this->subscribeRedirect();
        } catch (\Throwable $e) {
            // Anything else: log and return the generic
            // response. Do not leak the underlying cause to
            // the customer, and do not leak it to an
            // attacker probing the endpoint.
            $this->container->get(\CorporateAid\Platform\Support\Logger::class)
                ->error('Newsletter subscribe failed', [
                    'error' => $e->getMessage(),
                ]);
        }

        return $this->subscribeRedirect();
    }

    /**
     * GET /newsletter/confirm/{token}
     *
     * Verifies the token and moves the subscriber to
     * confirmed. Renders a neutral result page for every
     * outcome.
     */
    /** @param array<string,string> $params */
    public function showConfirm(Request $request, array $params = []): Response
    {
        $token   = (string) ($params['token'] ?? '');
        $service = $this->container->get(SubscriberService::class);
        $result  = $service->confirmByToken($token);

        return $this->render('storefront.newsletter.confirm-result', [
            'title'  => $result['ok'] ? 'Subscription confirmed' : 'Link not valid',
            'ok'     => $result['ok'],
            'message' => $result['message'],
        ]);
    }

    /**
     * GET /newsletter/unsubscribe/{token}
     *
     * Renders a confirmation form. The unsubscribe is NOT
     * performed on GET. Enterprise mail scanners click every
     * link in every email; if GET unsubscribed, they would
     * silently remove every recipient.
     */
    /** @param array<string,string> $params */
    public function showUnsubscribe(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');

        // Verify the token up-front so we do not render a
        // form the customer cannot submit. On failure render
        // the same neutral error page as a bad confirm link.
        $tokens = $this->container->get(\CorporateAid\Platform\Support\SignedToken::class);
        $claims = $tokens->verify($token, 'unsubscribe');

        if ($claims === null) {
            return $this->render('storefront.newsletter.confirm-result', [
                'title'   => 'Link not valid',
                'ok'      => false,
                'message' => 'This unsubscribe link is no longer valid.',
            ]);
        }

        return $this->render('storefront.newsletter.unsubscribe-form', [
            'title' => 'Unsubscribe',
            'token' => $token,
        ]);
    }

    /**
     * POST /newsletter/unsubscribe/{token}
     *
     * Performs the unsubscribe. The token is re-verified here
     * even though it was verified on the GET; the POST must
     * stand on its own.
     */
    /** @param array<string,string> $params */
    public function performUnsubscribe(Request $request, array $params = []): Response
    {
        $token   = (string) ($params['token'] ?? '');
        $reason  = trim((string) $request->input('reason', ''));
        $service = $this->container->get(SubscriberService::class);

        $result = $service->unsubscribeByToken(
            $token,
            $reason !== '' ? $reason : null
        );

        return $this->render('storefront.newsletter.confirm-result', [
            'title'   => $result['ok'] ? 'Unsubscribed' : 'Link not valid',
            'ok'      => $result['ok'],
            'message' => $result['message'],
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Flash a generic newsletter result and redirect back to
     * the home page. Deliberately does not leak which
     * outcome occurred.
     */
    private function subscribeRedirect(): Response
    {
        $this->flash(
            'success',
            'Thanks. If the address is new, you will receive a confirmation email.'
        );

        return Response::redirect('/');
    }

    /**
     * Write a flash without resolving the customer session.
     * The storefront layout reads flash from the default
     * session, which is the admin session in this codebase.
     * See the flash helpers in App.
     *
     * @param mixed $value
     */
    private function flash(string $key, $value): void
    {
        $session = $this->container->get(\CorporateAid\Core\Session::class);
        $session->flash($key, $value);
    }
}
