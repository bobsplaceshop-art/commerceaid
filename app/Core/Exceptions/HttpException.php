<?php

declare(strict_types=1);

namespace CorporateAid\Core\Exceptions;

use RuntimeException;

/**
 * Thrown to signal a specific HTTP status code (404, 403, 500, etc.).
 * The App class catches it and renders the matching error view.
 */
class HttpException extends RuntimeException
{
    private int $statusCode;
    /** @var array<string,string> */
    private array $headers;

    /**
     * True when this exception was thrown by the Router
     * because no route matched the incoming path. Distinct
     * from a controller-thrown 404 for a matched route whose
     * resource does not exist.
     *
     * The distinction matters for logging: an unmatched route
     * is a browser default request (favicon), an extension's
     * injected asset, or a scanner probe. None of those are
     * our problems and none of them should fill the log.
     * A controller-thrown 404 is a broken link or a deleted
     * resource, and it is worth a log line.
     */
    private bool $unmatchedRoute = false;

    /** @param array<string,string> $headers */
    public function __construct(int $statusCode, string $message = '', array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Response headers to send with the error page.
     *
     * Currently unused by the framework error handler - kept as
     * part of the exception's API because it is the correct place
     * to carry headers for statuses that require them: Location
     * for 301/302, WWW-Authenticate for 401, Retry-After for 429.
     * The convenience factories below do not pass headers today;
     * a future thrower that needs them calls the constructor
     * directly.
     *
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * True when the Router threw this because no route
     * matched. False for a controller-thrown notFound() on a
     * matched route.
     */
    public function isUnmatchedRoute(): bool
    {
        return $this->unmatchedRoute;
    }

    // Convenience factories
    public static function notFound(string $message = 'Page not found'): self
    {
        return new self(404, $message);
    }

    /**
     * No route matched the path. The Router uses this. It
     * carries the same 404 status as notFound() but is
     * logged differently - see App::renderException().
     */
    public static function unmatchedRoute(string $message = 'Page not found'): self
    {
        $e = new self(404, $message);
        $e->unmatchedRoute = true;
        return $e;
    }

    public static function forbidden(string $message = 'You do not have permission to view this page'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Please log in to continue'): self
    {
        return new self(401, $message);
    }

    public static function badRequest(string $message = 'Bad request'): self
    {
        return new self(400, $message);
    }
}
