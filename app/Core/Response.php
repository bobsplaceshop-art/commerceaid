<?php
declare(strict_types=1);

namespace CorporateAid\Core;

/**
 * Represents an outgoing HTTP response.
 */
final class Response
{
    private int $statusCode;
    /** @var array<string,string> */
    private array $headers;
    private string $body;

    /** @param array<string,string> $headers */
    public function __construct(string $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new self($body ?: '{}', $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * Serve a file from an absolute path.
     *
     * Reads the file into memory. The current cap is 5 MB
     * per upload, so this is safe. If video or larger
     * assets are added later, switch to a streaming
     * implementation that does not buffer the file.
     *
     * Sets Content-Type from the stored mime and
     * Cache-Control to public, one year, immutable. UUIDs
     * are stable and file contents never change for a
     * given UUID, so aggressive caching is correct.
     *
     * @param array<string,string> $extraHeaders
     */
    public static function file(
        string $absolutePath,
        string $mimeType,
        array $extraHeaders = []
    ): self {
        if (!is_file($absolutePath)) {
            return new self('Not found', 404, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        $body = @file_get_contents($absolutePath);
        if ($body === false) {
            return new self('Could not read file', 500, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        $headers = array_merge([
            'Content-Type'   => $mimeType,
            'Cache-Control'  => 'public, max-age=31536000, immutable',
        ], $extraHeaders);

        return new self($body, 200, $headers);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** @param array<string,string> $headers */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $k => $v) {
            $this->headers[$k] = $v;
        }
        return $this;
    }

    public function statusCode(): int { return $this->statusCode; }
    public function body(): string    { return $this->body; }
    /** @return array<string,string> */
    public function headers(): array  { return $this->headers; }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        // HTTP HEAD: emit headers, skip body. This is the
        // framework's HTTP boundary, so the check lives here
        // instead of in every controller. Any response can be
        // returned by a controller; send() decides whether the
        // body is emitted based on the request method.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            return;
        }

        echo $this->body;
    }
}
