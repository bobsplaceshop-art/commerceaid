<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment;

use CorporateAid\Platform\Support\Logger;

/**
 * Hand-rolled HTTP client for payment provider APIs.
 *
 * No Composer dependency. Uses curl_* directly, matching
 * the pattern FontDownloader established for the only
 * other outbound HTTP call in the codebase.
 *
 * Diverges from FontDownloader in two deliberate ways:
 *
 *   - CURLOPT_FAILONERROR is false. A 4xx response from a
 *     payment provider carries a JSON error body that the
 *     caller needs to read and log. Failing on HTTP status
 *     would throw that body away.
 *
 *   - CURLOPT_FOLLOWLOCATION is false. Payment APIs do not
 *     redirect. A 3xx from a provider is a bug or a
 *     misconfiguration, and the caller should see it.
 *
 * Every response is returned as a normalised array:
 *   { status: int, body: array, raw: string, error: ?string }
 *
 * The `body` key is the JSON-decoded body. When the body is
 * not valid JSON, `body` is empty and `raw` carries the
 * bytes.
 *
 * Logging is at info level, one line per request. The line
 * carries the method, URL, status, and elapsed milliseconds.
 * It never carries the payload or the headers — those hold
 * API keys and customer data.
 *
 * Stage 010.
 */
final class HttpClient
{
    private const DEFAULT_TIMEOUT = 15;
    private const DEFAULT_CONNECT_TIMEOUT = 5;
    private const USER_AGENT = 'CommerceAid/PaymentClient';

    /**
     * POST a JSON-encoded payload. Returns the normalised
     * response array described at the class docblock.
     *
     * The payload type accepts an array or an empty stdClass.
     * The stdClass form is needed when a provider (PayPal's
     * capture endpoint) requires an empty JSON object in the
     * body rather than an empty JSON array. An empty PHP
     * array [] encodes to "[]", not "{}". new \stdClass()
     * encodes to "{}". PayPal rejects the array form.
     *
     * @param array<string,mixed>|\stdClass  $payload
     * @param array<int, string> $headers   Each entry
     *        is a full header line, e.g. "Authorization: Bearer x".
     *
     * @return array{status: int, body: array<string,mixed>,
     *               raw: string, error: ?string}
     */
    public function postJson(string $url, array|\stdClass $payload, array $headers = [], int $timeout = self::DEFAULT_TIMEOUT): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return [
                'status' => 0,
                'body'   => [],
                'raw'    => '',
                'error'  => 'Could not encode request payload.',
            ];
        }

        $headers = array_merge(['Content-Type: application/json'], $headers);
        return $this->request('POST', $url, $json, $headers, $timeout);
    }

    /**
     * POST a raw string body. Used when the provider's
     * contract requires form-encoded input.
     *
     * @param array<int, string> $headers
     *
     * @return array{status: int, body: array<string,mixed>,
     *               raw: string, error: ?string}
     */
    public function post(string $url, string $body, array $headers = [], int $timeout = self::DEFAULT_TIMEOUT): array
    {
        return $this->request('POST', $url, $body, $headers, $timeout);
    }

    /**
     * GET with headers. Returns the normalised response.
     *
     * @param array<int, string> $headers
     *
     * @return array{status: int, body: array<string,mixed>,
     *               raw: string, error: ?string}
     */
    public function get(string $url, array $headers = [], int $timeout = self::DEFAULT_TIMEOUT): array
    {
        return $this->request('GET', $url, null, $headers, $timeout);
    }

    /**
     * The single request path. Every public method funnels
     * here.
     *
     * @param array<int, string> $headers
     *
     * @return array{status: int, body: array<string,mixed>,
     *               raw: string, error: ?string}
     */
    private function request(string $method, string $url, ?string $body, array $headers, int $timeout): array
    {
        if (!function_exists('curl_init')) {
            return [
                'status' => 0,
                'body'   => [],
                'raw'    => '',
                'error'  => 'curl extension is not available.',
            ];
        }

        $started = microtime(true);

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'status' => 0,
                'body'   => [],
                'raw'    => '',
                'error'  => 'Could not initialise the HTTP client.',
            ];
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => max(1, $timeout),
            CURLOPT_CONNECTTIMEOUT => self::DEFAULT_CONNECT_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FAILONERROR    => false,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body ?? '';
        } elseif ($method === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }
        }

        // Drop empty header lines. Header strings are
        // never empty in practice, but a caller could
        // pass one. Filtering keeps the runtime shape
        // honest against the array_filter below.
        $options[CURLOPT_HTTPHEADER] = array_values(array_filter(
            $options[CURLOPT_HTTPHEADER],
            static fn ($h) => $h !== ''
        ));

        // PHPStan's curl_setopt_array signature narrows each
        // option offset to a specific type. CURLOPT_HTTPHEADER
        // is typed non-empty-string|null in the stub, but the
        // runtime accepts an array of strings. Widening
        // $options here documents the true shape.
        /** @var array<int, mixed> $curlOptions */
        $curlOptions = $options;
        curl_setopt_array($ch, $curlOptions);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errNo  = curl_errno($ch);
        $errMsg = curl_error($ch);
        curl_close($ch);

        $elapsedMs = (int) round((microtime(true) - $started) * 1000);

        if ($raw === false || $errNo !== 0) {
            Logger::info('http.request', [
                'method'     => $method,
                'url'        => $url,
                'status'     => $status,
                'elapsed_ms' => $elapsedMs,
                'error'      => $errMsg !== '' ? $errMsg : 'request failed',
            ]);

            return [
                'status' => $status,
                'body'   => [],
                'raw'    => '',
                'error'  => $errMsg !== '' ? $errMsg : 'HTTP request failed.',
            ];
        }

        $rawString = is_string($raw) ? $raw : '';
        $decoded   = [];
        if ($rawString !== '') {
            $maybe = json_decode($rawString, true);
            if (is_array($maybe)) {
                $decoded = $maybe;
            }
        }

        Logger::info('http.request', [
            'method'     => $method,
            'url'        => $url,
            'status'     => $status,
            'elapsed_ms' => $elapsedMs,
            'bytes'      => strlen($rawString),
        ]);

        return [
            'status' => $status,
            'body'   => $decoded,
            'raw'    => $rawString,
            'error'  => null,
        ];
    }
}
