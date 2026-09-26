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

/**
 * Admin authentication.
 */
final class AuthController
{
    private const DEFAULT_REDIRECT = '/commerce/ca-admin';

    public function __construct(private Container $container)
    {
    }

    public function showLogin(Request $request, array $params = []): Response
    {
        $view    = $this->container->get(View::class);
        $csrf    = $this->container->get(Csrf::class);
        $session = $this->container->get(Session::class);

        $intended = (string) $request->query('intended', '');
        if (!$this->isSafeInternalRedirect($intended)) {
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

        if (!$this->isSafeInternalRedirect($intended) || $intended === '/logout') {
            $intended = self::DEFAULT_REDIRECT;
        }

        return Response::redirect($intended);
    }


    public function logout(Request $request, array $params = []): Response
    {
        $auth = $this->container->get(Auth::class);
        $auth->logout();
        return Response::redirect('/login');
    }

    private function isSafeInternalRedirect(string $url): bool
    {
        if ($url === '') return false;
        if ($url[0] !== '/') return false;
        if (str_starts_with($url, '//')) return false;
        if (str_starts_with($url, '/\\')) return false;
        if (str_contains($url, "\n") || str_contains($url, "\r")) return false;
        return true;
    }

    private function intendedQuery(Request $request): string
    {
        $intended = (string) $request->input('intended', '');
        if ($intended === '' || !$this->isSafeInternalRedirect($intended)) {
            return '';
        }
        return '?intended=' . rawurlencode($intended);
    }
}
