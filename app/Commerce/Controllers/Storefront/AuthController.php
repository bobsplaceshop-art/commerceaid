<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Models\Customer;
use CorporateAid\Commerce\Repositories\CustomerGroupRepository;
use CorporateAid\Commerce\Repositories\CustomerRepository;
use CorporateAid\Commerce\Services\CustomerAuthService;
use CorporateAid\Commerce\Services\CartService;
use CorporateAid\Commerce\Services\WishlistService;
use CorporateAid\Commerce\Services\RecentViewService;
use CorporateAid\Commerce\Services\CustomerNotificationService;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Commerce\Support\Validator;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Platform\Support\Hasher;
use CorporateAid\Platform\Support\Logger;
use CorporateAid\Platform\Support\RedirectGuard;

/**
 * Customer-side authentication.
 *
 * Parallel to Commerce\Controllers\AuthController but operating on
 * the customer table and the customer session. Different login
 * endpoint (/account/login, not /login), different user model,
 * different session cookie (ca_customer_session, not ca_session).
 *
 * Routes (all under /account):
 *   GET  /account/register          registration form
 *   POST /account/register          register action
 *   GET  /account/login             login form
 *   POST /account/login             login action
 *   POST /account/logout            logout action
 *   GET  /account/forgot-password   forgot form
 *   POST /account/forgot-password   send reset link
 *   GET  /account/reset-password    reset form (with ?token=...)
 *   POST /account/reset-password    reset action
 *
 * Rate limiting is applied at the route level via the 'ratelimit'
 * middleware (see routes/storefront.php). The controller does not
 * call RateLimiter directly. This matches how the admin login
 * route rate-limits.
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */
final class AuthController extends StorefrontController
{
    // =================================================================
    // Registration
    // =================================================================

    /** @param array<string,string> $params */
    public function showRegister(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $groups  = $this->container->get(CustomerGroupRepository::class);

        $groupOptions = [];
        foreach ($groups->listAll(false) as $g) {
            $groupOptions[] = [
                'id'                 => $g->id,
                'name'               => $g->name,
                'requires_approval'  => $g->requiresApproval,
                'is_b2b'             => $g->isB2b,
            ];
        }

        return $this->render('storefront.auth.register', [
            'title'        => 'Create an account',
            'groupOptions' => $groupOptions,
            'errors'       => $session->getFlash('register_errors', []),
            'old'          => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function register(Request $request, array $params = []): Response
    {
        $session  = $this->container->get(Session::class);
        $repo     = $this->container->get(CustomerRepository::class);
        $groups   = $this->container->get(CustomerGroupRepository::class);
        $customerAuth = $this->container->get(CustomerAuthService::class);

        $data = [
            'email'              => strtolower(trim((string) $request->input('email', ''))),
            'password'           => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
            'first_name'         => trim((string) $request->input('first_name', '')),
            'last_name'          => trim((string) $request->input('last_name', '')),
            'phone'              => trim((string) $request->input('phone', '')),
            'company'            => trim((string) $request->input('company', '')),
            'accepts_marketing'  => (bool) $request->input('accepts_marketing', false),
            'customer_group_id'  => (int) $request->input('customer_group_id', 0),
        ];

        $errors = $this->validateRegistration($data, $repo);

        if ($errors !== []) {
            $session->flash('register_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/account/register');
        }

        // Resolve the group. If the form passed one, verify it exists.
        // Otherwise fall back to the store's default group.
        $group = null;
        if ($data['customer_group_id'] > 0) {
            $group = $groups->findById($data['customer_group_id']);
        }
        if ($group === null) {
            $group = $groups->findDefault();
        }

        // Approval state is inherited from the group's requires_approval.
        // No group -> default group -> typically no approval required.
        $approvalStatus = 'approved';
        if ($group !== null && $group->requiresApproval) {
            $approvalStatus = 'pending';
        }

        $customer = new Customer();
        $customer->email              = $data['email'];
        $customer->passwordHash       = Hasher::hash($data['password']);
        $customer->firstName          = $data['first_name'] !== '' ? $data['first_name'] : null;
        $customer->lastName           = $data['last_name'] !== '' ? $data['last_name'] : null;
        $customer->phone              = $data['phone'] !== '' ? $data['phone'] : null;
        $customer->company            = $data['company'] !== '' ? $data['company'] : null;
        $customer->acceptsMarketing   = $data['accepts_marketing'];
        $customer->customerGroupId    = $group?->id;
        $customer->status             = 'active';
        $customer->approvalStatus     = $approvalStatus;

        $customerId = $repo->create($customer);
        $customer->id = $customerId;

        // Auto-login even if pending. Pending customers can browse.
        // The checkout gate is enforced by Customer::canCheckout().
        $customerAuth->login($customer);

        // Same merge as in login(). A guest who fills their cart
        // and then registers an account should not lose the cart.
        $this->container->get(CartService::class)->mergeGuestCartOnLogin();
        // Same merge for the wishlist. A guest who hearts products
        // and then logs in or registers keeps their wishlist.
        $this->container->get(WishlistService::class)->mergeGuestWishlistOnLogin();
        // Same merge for recently-viewed history.
        $this->container->get(RecentViewService::class)->mergeGuestHistoryOnLogin();

        // Welcome message. Fires only on registration, not on
        // login. The customer ID is the just-created one.
        $this->container->get(CustomerNotificationService::class)->welcome($customerId);

        $session->clearOldInput();

        if ($approvalStatus === 'pending') {
            $session->flash('success', 'Your account has been created and is pending review. You can browse and add items to your cart until it is approved.');
        } else {
            $session->flash('success', 'Welcome! Your account has been created.');
        }

        return Response::redirect('/account');
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validateRegistration(array $data, CustomerRepository $repo): array
    {
        $errors = [];

        if ($e = Validator::required($data['email'], 'Email')) {
            $errors['email'] = $e;
        } elseif ($e = Validator::email($data['email'], 'Email')) {
            $errors['email'] = $e;
        } elseif ($repo->emailExists((string) $data['email'])) {
            $errors['email'] = 'An account with this email already exists.';
        }

        $password = (string) $data['password'];
        if ($e = Validator::required($password, 'Password')) {
            $errors['password'] = $e;
        } elseif ($e = Validator::minLength($password, 8, 'Password')) {
            $errors['password'] = $e;
        } elseif ($password !== (string) $data['password_confirmation']) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        if ($e = Validator::required($data['first_name'], 'First name')) {
            $errors['first_name'] = $e;
        } elseif ($e = Validator::maxLength($data['first_name'], 100, 'First name')) {
            $errors['first_name'] = $e;
        }

        if ($e = Validator::required($data['last_name'], 'Last name')) {
            $errors['last_name'] = $e;
        } elseif ($e = Validator::maxLength($data['last_name'], 100, 'Last name')) {
            $errors['last_name'] = $e;
        }

        if ($data['phone'] !== '' && ($e = Validator::maxLength($data['phone'], 50, 'Phone'))) {
            $errors['phone'] = $e;
        }

        if ($data['company'] !== '' && ($e = Validator::maxLength($data['company'], 200, 'Company'))) {
            $errors['company'] = $e;
        }

        return $errors;
    }

    // =================================================================
    // Login / logout
    // =================================================================

    /** @param array<string,string> $params */
    public function showLogin(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $customerAuth = $this->container->get(CustomerAuthService::class);

        // Already logged in? Send them to the account page.
        if ($customerAuth->check()) {
            return Response::redirect('/account');
        }

        $intended = (string) $request->query('intended', '');
        if (!RedirectGuard::isSafeInternalRedirect($intended)) {
            $intended = '';
        }

        return $this->render('storefront.auth.login', [
            'title'    => 'Sign in',
            'intended' => $intended,
            'errors'   => $session->getFlash('login_errors', []),
            'old'      => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function login(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $customerAuth = $this->container->get(CustomerAuthService::class);

        $email    = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        $errors = [];
        if ($e = Validator::required($email, 'Email')) {
            $errors['email'] = $e;
        } elseif ($e = Validator::email($email, 'Email')) {
            $errors['email'] = $e;
        }
        if ($e = Validator::required($password, 'Password')) {
            $errors['password'] = $e;
        }

        if ($errors !== []) {
            $session->flash('login_errors', $errors);
            $session->flashOldInput(['email' => $email]);
            return Response::redirect('/account/login' . $this->intendedQuery($request));
        }

        $customer = $customerAuth->attempt($email, $password);
        if ($customer === null) {
            // Generic error - do not distinguish wrong email from
            // wrong password from inactive account.
            $session->flash('login_errors', ['_form' => 'Invalid email or password.']);
            $session->flashOldInput(['email' => $email]);
            return Response::redirect('/account/login' . $this->intendedQuery($request));
        }

        // Merge any guest cart items into the customer's DB cart.
        // The customer is now logged in, so CartService resolves
        // to the DB-backed cart. Any items added as a guest are
        // moved into it and the session cart is cleared.
        //
        // Called from the controller rather than from
        // CustomerAuthService::login() to avoid a circular
        // dependency (CartService already depends on
        // CustomerAuthService).
        $this->container->get(CartService::class)->mergeGuestCartOnLogin();
        // Same merge for the wishlist. A guest who hearts products
        // and then logs in or registers keeps their wishlist.
        $this->container->get(WishlistService::class)->mergeGuestWishlistOnLogin();
        // Same merge for recently-viewed history.
        $this->container->get(RecentViewService::class)->mergeGuestHistoryOnLogin();

        $session->clearOldInput();

        $intended = (string) $request->input('intended', '');
        if (!RedirectGuard::isSafeInternalRedirect($intended)
            || $intended === '/account/logout'
            || $intended === '/account/login') {
            $intended = '/account';
        }

        $session->flash('success', 'Welcome back, ' . $customer->fullName() . '.');
        return Response::redirect($intended);
    }

    /** @param array<string,string> $params */
    public function logout(Request $request, array $params = []): Response
    {
        $customerAuth = $this->container->get(CustomerAuthService::class);
        $session = $this->container->get(Session::class);

        $customerAuth->logout();
        $session->flash('success', 'You have been signed out.');
        return Response::redirect('/');
    }

    // =================================================================
    // Forgot password
    // =================================================================

    /** @param array<string,string> $params */
    public function showForgotPassword(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);

        return $this->render('storefront.auth.forgot-password', [
            'title'  => 'Reset your password',
            'errors' => $session->getFlash('forgot_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
        ]);
    }

    /**
     * POST /account/forgot-password
     *
     * Generates a single-use reset token and logs the reset link
     * to storage/logs/app.log at INFO level.
     *
     * EMAIL SENDING IS NOT YET IMPLEMENTED. In production this flow
     * will send an email. For now, the operator greps the log for
     * "Password reset link generated" to retrieve the URL during
     * testing. See docs/stages/008c_customer-auth-plan.md.
     */
    /** @param array<string,string> $params */
    public function sendResetLink(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(CustomerRepository::class);

        $email = strtolower(trim((string) $request->input('email', '')));

        $errors = [];
        if ($e = Validator::required($email, 'Email')) {
            $errors['email'] = $e;
        } elseif ($e = Validator::email($email, 'Email')) {
            $errors['email'] = $e;
        }

        if ($errors !== []) {
            $session->flash('forgot_errors', $errors);
            $session->flashOldInput(['email' => $email]);
            return Response::redirect('/account/forgot-password');
        }

        $customer = $repo->findByEmail($email);

        // Regardless of whether the customer exists, show the same
        // message. This prevents email enumeration - an attacker
        // cannot determine which addresses have accounts.
        if ($customer !== null && $customer->isActive()) {
            // Invalidate any outstanding tokens for this customer.
            // Only the most recently requested reset link will work.
            $repo->invalidatePasswordResets($customer->id);

            // Generate the raw token. Store only the SHA-256 hash.
            // The raw token goes into the reset URL (logged for now,
            // emailed in a future stage). A database dump cannot be
            // used to forge a reset.
            $rawToken  = Hasher::randomToken(32);
            $tokenHash = hash('sha256', $rawToken);

            // TTL is read from config. MySQL computes the actual
            // expires_at value via DATE_ADD(NOW(), INTERVAL N MINUTE)
            // so PHP and the DB never disagree about what "now" is.
            // Local dev may raise this while iterating on the reset
            // flow; production stays at 60.
            $ttlMinutes = (int) config('app.customer_password_reset_ttl_minutes', 60);
            $repo->createPasswordReset($customer->id, $tokenHash, $ttlMinutes);

            $resetUrl = '/account/reset-password?token=' . $rawToken;

            Logger::info('Password reset link generated', [
                'customer_id' => $customer->id,
                'email'       => $customer->email,
                'reset_url'   => $resetUrl,
                'ttl_minutes' => $ttlMinutes,
            ]);

            // Plain-text companion line for easy copy-paste during
            // dev. No JSON wrapper, no trailing punctuation. Grep
            // for RESET-LINK-FOR-COPY to find the exact URL:
            //
            //   grep "RESET-LINK-FOR-COPY" storage/logs/app.log | tail -1
            //
            // The URL is everything after the last space on the
            // line. When real email sending lands, this line goes
            // away.
            Logger::info('RESET-LINK-FOR-COPY ' . $resetUrl);
        }

        $session->clearOldInput();
        $session->flash('success', 'If an account exists for that email, a reset link has been sent.');
        return Response::redirect('/account/login');
    }

    // =================================================================
    // Reset password
    // =================================================================

    /** @param array<string,string> $params */
    public function showResetPassword(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(CustomerRepository::class);

        $token = (string) $request->query('token', '');

        $reset = $token !== ''
            ? $repo->findActivePasswordReset(hash('sha256', $token))
            : null;

        if ($reset === null) {
            $session->flash('error', 'This password reset link is invalid or has expired. Request a new one.');
            return Response::redirect('/account/forgot-password');
        }

        return $this->render('storefront.auth.reset-password', [
            'title'  => 'Set a new password',
            'token'  => $token,
            'errors' => $session->getFlash('reset_errors', []),
        ]);
    }

    /** @param array<string,string> $params */
    public function resetPassword(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(CustomerRepository::class);

        $token    = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');
        $confirm  = (string) $request->input('password_confirmation', '');

        $reset = $token !== ''
            ? $repo->findActivePasswordReset(hash('sha256', $token))
            : null;

        if ($reset === null) {
            $session->flash('error', 'This password reset link is invalid or has expired. Request a new one.');
            return Response::redirect('/account/forgot-password');
        }

        $errors = [];
        if ($e = Validator::required($password, 'Password')) {
            $errors['password'] = $e;
        } elseif ($e = Validator::minLength($password, 8, 'Password')) {
            $errors['password'] = $e;
        } elseif ($password !== $confirm) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            $session->flash('reset_errors', $errors);
            return Response::redirect('/account/reset-password?token=' . rawurlencode($token));
        }

        $repo->setPassword($reset['customer_id'], Hasher::hash($password));
        $repo->consumePasswordReset($reset['id']);

        $session->flash('success', 'Your password has been reset. You can now sign in.');
        return Response::redirect('/account/login');
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Preserve ?intended= across a failed login so the redirect
     * target survives the round trip.
     */
    private function intendedQuery(Request $request): string
    {
        $intended = (string) $request->input('intended', '');
        if ($intended === '' || !RedirectGuard::isSafeInternalRedirect($intended)) {
            return '';
        }
        return '?intended=' . rawurlencode($intended);
    }
}
