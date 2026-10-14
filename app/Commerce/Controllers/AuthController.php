<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Csrf;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Commerce\Support\Validator;
use CorporateAid\Platform\Repositories\PasswordResetRepository;
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Platform\Support\EmailService;
use CorporateAid\Platform\Support\RedirectGuard;
use CorporateAid\Platform\Support\AppUrl;

/**
 * Admin authentication.
 */
final class AuthController
{
    private const DEFAULT_REDIRECT = '/commerce/ca-admin';

    public function __construct(private Container $container)
    {
    }

    /** @param array<string,string> $params */
    public function showLogin(Request $request, array $params = []): Response
    {
        $view    = $this->container->get(View::class);
        $csrf    = $this->container->get(Csrf::class);
        $session = $this->container->get(Session::class);

        $intended = (string) $request->query('intended', '');
        if (!RedirectGuard::isSafeInternalRedirect($intended)) {
            $intended = '';
        }

        return Response::html(
            $view->render('auth.login', [
                'title'    => 'Sign in',
                'csrf'     => $csrf->token(),
                'errors'   => $session->getFlash('login_errors', []),
                'old'      => $_SESSION['_old'] ?? [],
                'intended' => $intended,
            ], 'layouts.auth')
        );
    }

    /** @param array<string,string> $params */
    public function login(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $auth    = $this->container->get(Auth::class);
        $csrf    = $this->container->get(Csrf::class);

        $email    = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        $errors = [];

        if ($e = Validator::required($email, 'Email')) {
            $errors['email'] = $e;
        } elseif ($e = Validator::email($email, 'Email')) {
            $errors['email'] = $e;
        } elseif (mb_strlen($email) > 190) {
            $errors['email'] = 'Email must be 190 characters or fewer.';
        }

        if ($e = Validator::required($password, 'Password')) {
            $errors['password'] = $e;
        } elseif (strlen($password) > 200) {
            // Cap password length to prevent DoS through expensive hashing.
            // Argon2/bcrypt truncate at 72 bytes internally anyway.
            $errors['password'] = 'Password is too long.';
        }

        if ($errors !== []) {
            $session->flash('login_errors', $errors);
            $session->flashOldInput(['email' => $email]);
            return Response::redirect('/login' . $this->intendedQuery($request));
        }

        $user = $auth->attempt($email, $password);
        if ($user === null) {
            $session->flash('login_errors', ['_form' => 'Invalid email or password.']);
            $session->flashOldInput(['email' => $email]);
            return Response::redirect('/login' . $this->intendedQuery($request));
        }

        $csrf->rotate();
        $session->clearOldInput();

        $intended = (string) $request->input('intended', '');

        if (!RedirectGuard::isSafeInternalRedirect($intended) || $intended === '/logout') {
            $intended = self::DEFAULT_REDIRECT;
        }

        return Response::redirect($intended);
    }


    /** @param array<string,string> $params */
    public function logout(Request $request, array $params = []): Response
    {
        $auth = $this->container->get(Auth::class);
        $auth->logout();
        return Response::redirect('/logout/done');
    }

    /**
     * Post-logout confirmation page. No middleware. The user is
     * already signed out at this point, so 'guest' would pass
     * anyway, but the page reads nothing from session state and
     * does not need the guard.
     *
     * @param array<string,string> $params
     */
    public function showLogoutDone(Request $request, array $params = []): Response
    {
        $view = $this->container->get(View::class);

        return Response::html(
            $view->render('auth.logout-done', [
                'title' => 'Signed out',
            ], 'layouts.auth')
        );
    }

    // =================================================================
    // Password reset (forgot / reset)
    // =================================================================

    /** @param array<string,string> $params */
    public function showForgot(Request $request, array $params = []): Response
    {
        $view    = $this->container->get(View::class);
        $session = $this->container->get(Session::class);

        return Response::html(
            $view->render('auth.forgot-password', [
                'title'  => 'Forgot password',
                'sentTo' => $session->getFlash('reset_sent_to'),
                'error'  => $session->getFlash('forgot_error'),
                'email'  => (string) ($_SESSION['_old']['email'] ?? ''),
            ], 'layouts.auth')
        );
    }

    /** @param array<string,string> $params */
    public function sendForgot(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $users   = $this->container->get(UserRepository::class);
        $reset   = $this->container->get(PasswordResetRepository::class);
        $mailer  = $this->container->get(EmailService::class);

        $email = strtolower(trim((string) $request->input('email', '')));

        // Always show the same success state, whether or not the
        // email matches an account. Preventing enumeration.
        if ($email === '' || Validator::email($email, 'Email') !== null) {
            $session->flash('forgot_error', 'Enter a valid email address.');
            $session->flashOldInput(['email' => $email]);
            return Response::redirect('/password/forgot');
        }

        $user = $users->findByEmail($email);

        // Only send if the user exists AND is active. Pending,
        // disabled, and suspended accounts do not get reset links.
        // Silence is the correct response - the user cannot
        // distinguish "no account" from "inactive account".
        if ($user !== null && $user->isActive()) {
            $token = $reset->createToken($email);
            $resetUrl = AppUrl::absolute('/login/reset/' . $token);

            $mailer->sendTemplate(
                $email,
                'Reset your ' . brand_name(null) . ' password',
                'password-reset',
                [
                    'user_name'  => $user->name,
                    'reset_url'  => $resetUrl,
                    'app_name'   => brand_name(null),
                    'expires_in' => '1 hour',
                ]
            );
        }

        $session->clearOldInput();
        $session->flash('reset_sent_to', $email);
        return Response::redirect('/password/forgot');
    }

    /** @param array<string,string> $params */
    public function showReset(Request $request, array $params = []): Response
    {
        $view  = $this->container->get(View::class);
        $reset = $this->container->get(PasswordResetRepository::class);
        $users = $this->container->get(UserRepository::class);
        $session = $this->container->get(Session::class);

        $token = (string) ($params['token'] ?? '');
        $email = $token !== '' ? $reset->findEmailByToken($token) : null;
        $user  = $email !== null ? $users->findByEmail($email) : null;

        // Invalid or expired token, or the user is not active.
        if ($user === null || !$user->isActive()) {
            return Response::html(
                $view->render('auth.reset-password', [
                    'title'        => 'Reset link expired',
                    'invalidToken' => true,
                    'error'        => $session->getFlash('reset_error'),
                ], 'layouts.auth')
            );
        }

        return Response::html(
            $view->render('auth.reset-password', [
                'title'        => 'Choose a new password',
                'invalidToken' => false,
                'email'        => $user->email,
                'token'        => $token,
                'error'        => $session->getFlash('reset_error'),
            ], 'layouts.auth')
        );
    }

    /** @param array<string,string> $params */
    public function consumeReset(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $reset   = $this->container->get(PasswordResetRepository::class);
        $users   = $this->container->get(UserRepository::class);
        $csrf    = $this->container->get(Csrf::class);

        $token    = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');
        $confirm  = (string) $request->input('password_confirm', '');

        $email = $token !== '' ? $reset->findEmailByToken($token) : null;
        $user  = $email !== null ? $users->findByEmail($email) : null;

        if ($user === null || !$user->isActive()) {
            $session->flash('reset_error', 'This reset link is no longer valid. Request a new one.');
            return Response::redirect('/password/forgot');
        }

        $errors = $this->validatePassword($password, $confirm);
        if ($errors !== null) {
            $session->flash('reset_error', $errors);
            return Response::redirect('/login/reset/' . rawurlencode($token));
        }

        $users->updatePassword($user->id, $password);
        $reset->consumeToken($token);

        $csrf->rotate();
        $session->flash('login_errors', ['_form' => 'Your password has been reset. Sign in with your new password.']);
        return Response::redirect('/login');
    }

    /**
     * Password strength check. Matches the hint shown in
     * resources/views/auth/reset-password.php.
     *
     * Rule: 12+ characters, OR 10+ characters with at least
     * 3 of 4 character classes (lowercase, uppercase, digit,
     * symbol).
     *
     * Returns null on success, or an error message on failure.
     */
    private function validatePassword(string $password, string $confirm): ?string
    {
        if ($password === '') {
            return 'Password is required.';
        }
        if ($password !== $confirm) {
            return 'Passwords do not match.';
        }
        if (strlen($password) > 200) {
            return 'Password is too long.';
        }

        $len     = strlen($password);
        $classes = 0;
        if (preg_match('/[a-z]/', $password)) $classes++;
        if (preg_match('/[A-Z]/', $password)) $classes++;
        if (preg_match('/[0-9]/', $password)) $classes++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $classes++;

        if ($len >= 12) {
            return null;
        }
        if ($len >= 10 && $classes >= 3) {
            return null;
        }

        return 'Password must be at least 12 characters, or at least 10 characters with 3 of 4 character types (uppercase, lowercase, digit, symbol).';
    }

    private function intendedQuery(Request $request): string
    {
        $intended = (string) $request->input('intended', '');
        if ($intended === '' || !RedirectGuard::isSafeInternalRedirect($intended)) {
            return '';
        }
        return '?intended=' . rawurlencode($intended);
    }
}
