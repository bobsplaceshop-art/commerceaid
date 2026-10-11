<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Support;

use RuntimeException;

/**
 * HMAC-SHA256 signed tokens for newsletter confirm and
 * unsubscribe links.
 *
 * Format:
 *   base64url(json(claims)) . '.' . base64url(hmac_sha256(payload, key))
 *
 * The payload carries:
 *   id      - the subscriber id (and campaign id for unsubscribe)
 *   scope   - 'confirm' or 'unsubscribe'
 *   exp     - unix timestamp when the token expires
 *
 * No raw email address ever appears in a URL. The token is
 * the only thing in the query string.
 *
 * The signing key comes from config('app.key'). It must be at
 * least 32 bytes. In a fresh install, generate one with:
 *
 *   php -r "echo bin2hex(random_bytes(32));"
 *
 * and set it in config/local.php:
 *
 *   'app' => ['key' => '<the hex string>'],
 *
 * See docs/stages/008i_newsletter-plan.md ("Token strategy").
 */
final class SignedToken
{
    private const MIN_KEY_BYTES = 32;

    public function __construct(private string $key)
    {
        if (strlen($this->key) < self::MIN_KEY_BYTES) {
            throw new RuntimeException(
                'SignedToken: config("app.key") is missing or too short. '
                . 'It must be at least ' . self::MIN_KEY_BYTES . ' bytes. '
                . 'Generate one with: php -r "echo bin2hex(random_bytes(32));"'
            );
        }
    }

    /**
     * Sign a set of claims. The 'exp' claim is added if not
     * already set.
     *
     * @param array<string,mixed> $claims
     */
    public function sign(array $claims, int $ttlSeconds): string
    {
        $claims['exp'] = time() + $ttlSeconds;
        $payload       = $this->base64UrlEncode(
            (string) json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        $signature     = $this->base64UrlEncode(
            hash_hmac('sha256', $payload, $this->key, true)
        );

        return $payload . '.' . $signature;
    }

    /**
     * Verify a token. Returns the decoded claims on success,
     * null on any failure: malformed, wrong signature, wrong
     * scope, expired, or missing claims.
     *
     * The caller is expected to treat every failure identically
     * ("this link is no longer valid").
     *
     * @return array<string,mixed>|null
     */
    public function verify(string $token, string $expectedScope): ?array
    {
        if ($token === '') return null;

        $parts = explode('.', $token);
        if (count($parts) !== 2) return null;

        [$payload, $signature] = $parts;
        if ($payload === '' || $signature === '') return null;

        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', $payload, $this->key, true)
        );

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = $this->base64UrlDecode($payload);
        if ($decoded === null) return null;

        $claims = json_decode($decoded, true);
        if (!is_array($claims)) return null;

        if (($claims['scope'] ?? null) !== $expectedScope) {
            return null;
        }

        $exp = $claims['exp'] ?? null;
        if (!is_int($exp) || $exp <= time()) {
            return null;
        }

        return $claims;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): ?string
    {
        $padded = str_pad($data, (int) (ceil(strlen($data) / 4) * 4), '=', STR_PAD_RIGHT);
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
