<?php

declare(strict_types=1);

namespace CorporateAid\Core;

use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Http\Kernel;
use CorporateAid\Platform\Repositories\CustomFieldRepository;
use CorporateAid\Platform\Repositories\CustomFieldValueRepository;
use CorporateAid\Platform\Repositories\EntityFileRepository;
use CorporateAid\Platform\Repositories\FileRepository;
use CorporateAid\Platform\Repositories\NotificationRepository;
use CorporateAid\Platform\Repositories\PasswordResetRepository;
use CorporateAid\Platform\Repositories\RateLimitRepository;
use CorporateAid\Platform\Repositories\RoleRepository;
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Platform\Services\CustomFieldService;
use CorporateAid\Platform\Services\FileService;
use CorporateAid\Platform\Services\NotificationService;
use CorporateAid\Platform\Services\NotificationRuleService;
use CorporateAid\Platform\Services\SystemSettingService;
use CorporateAid\Platform\Deletion\DeletionService;
use CorporateAid\Platform\CustomFields\CustomFieldRenderer;
use CorporateAid\Platform\CustomFields\CustomFieldValueSaver;
use CorporateAid\Platform\CustomFields\CustomFieldValueValidator;
use CorporateAid\Platform\Context\StoreContextInterface;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\Support\AuditLogger;
use CorporateAid\Platform\Support\CodeGenerator;
use CorporateAid\Platform\Support\EmailService;
use CorporateAid\Platform\Support\Logger;
use CorporateAid\Platform\Support\RateLimiter;
use CorporateAid\Platform\Validation\CustomFieldValidator;
use CorporateAid\Platform\Validation\FileValidator;
use Throwable;

/**
 * App bootstrapper.
 *
 * Registers Core and Platform services. Delegates product-specific
 * registration to a product provider (e.g. CommerceServiceProvider).
 *
 * App.php has zero imports from any product namespace. This invariant
 * is what keeps Core and Platform product-agnostic.
 */
final class App
{
    public const VERSION = '0.2.0-alpha';

    /**
     * Product providers to load, in order. Each entry is a fully
     * qualified class name implementing the ServiceProvider contract.
     * Add a new entry to install a new product in this deployment.
     */
    private const PRODUCT_PROVIDERS = [
        \CorporateAid\Commerce\CommerceServiceProvider::class,
    ];

    private static ?self $instance = null;
    private string $basePath;
    private Container $container;
    private Router $router;

    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->container = new Container();
    }

    public static function boot(string $basePath): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }
        $app = new self($basePath);
        self::$instance = $app;
        $app->bootstrap();
        return $app;
    }

    public static function instance(): self
    {
        if (!self::$instance instanceof self) {
            throw new \RuntimeException('App has not been booted.');
        }
        return self::$instance;
    }

    private function bootstrap(): void
    {
        Logger::setBasePath($this->basePath);
        $this->registerErrorHandling();
        $this->loadConfig();
        $this->registerTimezone();
        $this->registerCoreServices();
        $this->registerPlatformServices();
        $this->registerProductProviders();
        $this->loadRoutes();
    }

    private function registerErrorHandling(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $e): void {
            try {
                Logger::exception($e);
            } catch (Throwable) {
                // logging must never break the response
            }
            $this->renderException($e);
        });
    }

    private function loadConfig(): void
    {
        $configDir = $this->basePath . '/config';
        $merged = [];
        foreach (glob($configDir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if ($name === 'local' || $name === 'cli') {
                continue;
            }
            $merged[$name] = require $file;
        }
        $local = $configDir . '/local.php';
        if (is_file($local)) {
            $overrides = require $local;
            if (is_array($overrides)) {
                $merged = array_replace_recursive($merged, $overrides);
            }
        }
        $this->container->instance('config', new Config($merged));
    }

    private function registerTimezone(): void
    {
        $tz = $this->container->get('config')->get('app.timezone', 'Africa/Nairobi');
        date_default_timezone_set($tz);
    }

    private function registerCoreServices(): void
    {
        $c = $this->container;

        $c->singleton(Database::class, fn() => new Database((array) $c->get('config')->get('database', [])));
        $c->singleton(View::class,     fn() => new View($this->basePath . '/resources/views', $c->get('config')));
        $c->singleton(Router::class,   fn() => new Router($c));
        $c->singleton(Session::class,  fn() => new Session((array) $c->get('config')->get('app.session', [])));
        $c->singleton(Csrf::class,     fn() => new Csrf($c->get(Session::class)));
        $c->singleton(Kernel::class,   fn() => new Kernel($c));

        $c->instance('basePath', $this->basePath);
        $c->instance('app.version', self::VERSION);
    }

    private function registerPlatformServices(): void
    {
        $c = $this->container;

        // Repositories
        $c->singleton(UserRepository::class,             fn() => new UserRepository($c->get(Database::class)));
        $c->singleton(RoleRepository::class,             fn() => new RoleRepository($c->get(Database::class)));
        $c->singleton(PasswordResetRepository::class,    fn() => new PasswordResetRepository($c->get(Database::class)));
        $c->singleton(RateLimitRepository::class,        fn() => new RateLimitRepository($c->get(Database::class)));
        $c->singleton(NotificationRepository::class,     fn() => new NotificationRepository($c->get(Database::class)));
        $c->singleton(CustomFieldRepository::class,      fn() => new CustomFieldRepository($c->get(Database::class)));
        $c->singleton(CustomFieldValueRepository::class, fn() => new CustomFieldValueRepository($c->get(Database::class)));
        $c->singleton(FileRepository::class,             fn() => new FileRepository($c->get(Database::class)));
        $c->singleton(EntityFileRepository::class,       fn() => new EntityFileRepository($c->get(Database::class)));

        // Auth
        $c->singleton(Auth::class, fn() => new Auth($c->get(UserRepository::class), $c->get(Session::class)));

        // Support
        $c->singleton(CodeGenerator::class,  fn() => new CodeGenerator($c->get(Database::class)));
        $c->singleton(EmailService::class,   fn() => new EmailService(
            (array) $c->get('config')->get('mail', []),
            new Logger()
        ));
        // The store context is bound by CommerceServiceProvider,
        // which registers after this provider. The binding
        // closure runs lazily on first get(), by which time
        // both layers have registered. The has() guard keeps
        // the binding safe in a bare-bones CLI context where
        // the Commerce layer never runs.
        $c->singleton(ActivityLogger::class, fn() => new ActivityLogger(
            $c->get(Database::class),
            $c->get(Auth::class),
            $c->has(StoreContextInterface::class)
                ? $c->get(StoreContextInterface::class)
                : null
        ));
        $c->singleton(AuditLogger::class,    fn() => new AuditLogger($c->get(Database::class)));

        // Validation
        $c->singleton(CustomFieldValidator::class, fn() => new CustomFieldValidator());
        $c->singleton(FileValidator::class,        fn() => new FileValidator());

        // Services
        $c->singleton(CustomFieldService::class, fn() => new CustomFieldService(
            $c->get(CustomFieldRepository::class),
            $c->get(CustomFieldValueRepository::class),
            $c->get(CustomFieldValidator::class),
            $c->get(ActivityLogger::class),
            $c->get(Auth::class)
        ));

        $c->singleton(DeletionService::class, fn() => new DeletionService(
            $c->get(Database::class),
            $c->get(ActivityLogger::class),
            $c->get(Auth::class)
        ));

        $c->singleton(FileService::class, fn() => new FileService(
            $c->get(FileRepository::class),
            $c->get(EntityFileRepository::class),
            $c->get(FileValidator::class),
            $this->basePath
        ));

        $c->singleton(NotificationRuleService::class, fn() => new NotificationRuleService(
            $c->get(Database::class),
            $c->get(Auth::class)
        ));

        $c->singleton(NotificationService::class, fn() => new NotificationService(
            $c->get(NotificationRepository::class),
            $c->get(UserRepository::class),
            $c->get(NotificationRuleService::class),
            $c->get(Auth::class)
        ));

        $c->singleton(SystemSettingService::class, function () use ($c) {
            $svc = new SystemSettingService(
                $c->get(Database::class),
                $c->get(ActivityLogger::class),
                $c->get(Auth::class)
            );
            $svc->setNotificationService($c->get(NotificationService::class));
            return $svc;
        });

        $c->singleton(RateLimiter::class, fn() => new RateLimiter(
            $c->get(RateLimitRepository::class),
            $c->get(SystemSettingService::class)
        ));

        // Custom field rendering
        $c->singleton(CustomFieldRenderer::class, fn() => new CustomFieldRenderer(
            $c->get(CustomFieldRepository::class),
            $c->get(CustomFieldValueRepository::class)
        ));
        $c->singleton(CustomFieldValueValidator::class, fn() => new CustomFieldValueValidator(
            $c->get(CustomFieldRepository::class)
        ));
        $c->singleton(CustomFieldValueSaver::class, fn() => new CustomFieldValueSaver(
            $c->get(CustomFieldRepository::class),
            $c->get(CustomFieldValueRepository::class),
            $c->get(FileService::class),
            $c->get(Auth::class)
        ));

        // Platform middleware
        $c->singleton(\CorporateAid\Platform\Middleware\AuthMiddleware::class,
            fn() => new \CorporateAid\Platform\Middleware\AuthMiddleware(
                $c->get(Auth::class),
                '/login'
            ));

        $c->singleton(\CorporateAid\Platform\Middleware\GuestMiddleware::class,
            fn() => new \CorporateAid\Platform\Middleware\GuestMiddleware(
                $c->get(Auth::class),
                '/'
            ));

        $c->singleton(\CorporateAid\Platform\Middleware\CsrfMiddleware::class,
            fn() => new \CorporateAid\Platform\Middleware\CsrfMiddleware($c->get(Csrf::class)));

        $c->singleton(\CorporateAid\Platform\Middleware\AuditLoggerMiddleware::class,
            fn() => new \CorporateAid\Platform\Middleware\AuditLoggerMiddleware($c));
    }

    private function registerProductProviders(): void
    {
        foreach (self::PRODUCT_PROVIDERS as $providerClass) {
            if (!class_exists($providerClass)) {
                continue;
            }
            $provider = new $providerClass();
            $provider->register($this->container);
        }
    }

    private function loadRoutes(): void
    {
        // Sessions are an HTTP concern. On CLI (the smoke
        // harness, the seed workers, the retry cron) there
        // are no cookies and no headers. Calling
        // Session->start() runs session_name(), which PHP
        // refuses once any byte has reached STDOUT. A CLI
        // process that echoes progress before boot would
        // crash on a warning it has no way to avoid. Skip
        // the session start on CLI.
        if (PHP_SAPI !== 'cli') {
            $this->container->get(Session::class)->start();
        }

        $this->router = $this->container->get(Router::class);
        $router = $this->router;

        // Files loaded in order. Missing files are silently skipped.
        $routeFiles = ['auth', 'web', 'storefront', 'commerce'];

        foreach ($routeFiles as $name) {
            $path = $this->basePath . '/routes/' . $name . '.php';
            if (is_file($path)) {
                require $path;
            }
        }

        // Product providers may register additional routes.
        foreach (self::PRODUCT_PROVIDERS as $providerClass) {
            if (!class_exists($providerClass)) {
                continue;
            }
            $provider = new $providerClass();
            $provider->routes($router);
        }
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function basePath(string $suffix = ''): string
    {
        return $this->basePath . ($suffix !== '' ? '/' . ltrim($suffix, '/') : '');
    }

    public function run(): void
    {
        $request = Request::capture();

        // Dev overlay opt-in. A developer toggles the debug
        // overlay by visiting any admin URL with ?ra-debug=1.
        // It stays on for the browser session until
        // ?ra-debug=0 turns it off.
        //
        // The opt-in is not a permission. It is a session
        // flag. The overlay's data source
        // (/commerce/ca-admin/_debug/recent-errors) still
        // requires core.audit.view. A merchant who has never
        // visited the query parameter never sees the overlay.
        //
        // Read through the Session class, not raw $_SESSION,
        // so the write path goes through the same wrapper the
        // rest of the framework uses.
        $debugToggle = $request->query('ra-debug', null);
        if ($debugToggle !== null) {
            try {
                $session = $this->container->get(\CorporateAid\Core\Session::class);
                if ((string) $debugToggle === '1') {
                    $session->set('ra_debug_overlay', true);
                } elseif ((string) $debugToggle === '0') {
                    $session->forget('ra_debug_overlay');
                }
            } catch (\Throwable) {
                // Session not available in some boot contexts
                // (very early errors). The overlay just does
                // not turn on. Never block the request.
            }
        }

        $response = $this->router->dispatch($request);
        $response->send();
    }

    private function renderException(Throwable $e): void
    {
        $errorRef = null;

        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $message = $e->getMessage();
        } else {
            $status = 500;
            $message = 'Something went wrong. Please try again or contact the administrator.';
            $errorRef = 'ERR-' . bin2hex(random_bytes(3));
        }

        // The X-Request-ID header on the error response links the
        // browser's Network tab to the log entry. The same ID is
        // in every log line for this request, attached by
        // Logger::log(). A support engineer with the request ID
        // greps once and lands on the exception.
        if (defined('CA_REQUEST_ID')) {
            @header('X-Request-ID: ' . CA_REQUEST_ID);
        }

        // Log level split. A 5xx is a server fault. It gets the
        // full structured exception line with reference and
        // trace. A 4xx is the router or a controller correctly
        // refusing a request - a missing page, a bad token, a
        // rate-limit rejection. Those are information-level
        // events, not exceptions. Logging them at exception
        // level pollutes the debug overlay and the exceptions
        // feed with entries that a developer does not need to
        // see. They stay in the app log at INFO level for the
        // audit trail.
        try {
            if ($status >= 500) {
                // 5xx: server fault. Full structured exception
                // with reference and trace.
                Logger::exception($e, $errorRef);
            } elseif ($e instanceof HttpException && $e->isUnmatchedRoute()) {
                // No route matched. This is a browser default
                // request (favicon), a browser-extension
                // injected asset, or a scanner probe. The
                // router did exactly what it should. Do not
                // log - this is not an application event.
            } else {
                // A route matched but the controller refused
                // it (404 on a deleted product, 403 on a
                // permission check, 429 on a rate limit).
                // Worth a log line at INFO for the audit trail.
                Logger::info('http ' . $status, [
                    'uri'    => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
                    'class'  => get_class($e),
                ]);
            }
        } catch (\Throwable) {
            // logging must never block error rendering
        }

        http_response_code($status);
        $view = $this->basePath . '/resources/views/errors/' . $status . '.php';
        if (!is_file($view)) {
            $view = $this->basePath . '/resources/views/errors/500.php';
        }
        if (!is_file($view)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "{$status} {$message}";
            if ($errorRef !== null) {
                echo " (Reference: {$errorRef})";
            }
            return;
        }
        $app = $this;
        $errorMessage = $message;

        // Brand name for the error page title. Guarded so a
        // bootstrap failure that has not yet loaded the helper
        // functions cannot make the error page itself fail.
        // This is the exact failure mode on 2026-10-14: the
        // helper append to functions.php did not land, every
        // page 500-ed, and the error pages could not render
        // because they called the missing function.
        $brandName = function_exists('brand_name')
            ? brand_name(null)
            : 'Store';

        require $view;
    }
}
