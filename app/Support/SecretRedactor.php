<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Removes provider credentials from free-form text before it is stored,
 * returned to a client or written to a log.
 *
 * Transport errors from HTTP clients usually embed the full request URL, and
 * several RPC providers (Pimlico, Helius, Alchemy, Infura) take their API key
 * in that URL. Every message that may carry such a URL goes through
 * {@see redact()} first.
 *
 * Redacted:
 *  - `apikey`, `api-key` and `api_key` values, as query parameters or as
 *    `key: value` / `"key":"value"` pairs (case-insensitive);
 *  - bearer tokens (`Bearer <token>`);
 *  - API keys carried in the path of Alchemy (`/v2/<key>`) and Infura
 *    (`/v3/<key>`) RPC URLs;
 *  - the literal values of the configured provider keys listed in
 *    {@see CONFIGURED_SECRET_KEYS}, wherever they appear.
 */
final class SecretRedactor
{
    public const MASK = '[redacted]';

    /**
     * Config keys whose values are provider credentials. Their literal values
     * are masked wherever they appear in a message.
     */
    private const CONFIGURED_SECRET_KEYS = [
        'relayer.pimlico.api_key',
        'relayer.bundler.providers.pimlico.api_key',
        'relayer.bundler.providers.stackup.api_key',
        'relayer.bundler.providers.alchemy.api_key',
        'relayer.balance_checking.alchemy_api_key',
        'relayer.balance_checking.infura_project_id',
        'services.helius.api_key',
    ];

    /**
     * Shorter values are not masked literally (too likely to match ordinary
     * text); the pattern rules still apply to them.
     */
    private const MIN_LITERAL_SECRET_LENGTH = 8;

    private function __construct()
    {
    }

    public static function redact(?string $message): string
    {
        if ($message === null || $message === '') {
            return '';
        }

        $patterns = [
            // apikey=..., api-key=..., api_key=..., "api_key":"...", api_key: ...
            '/\b(api[-_]?key)(["\']?\s*[:=]\s*["\']?)[^&#\s"\',;<>)}\]]+/i' => '$1$2' . self::MASK,
            // Authorization: Bearer <token>
            '/\b(Bearer)\s+[A-Za-z0-9\-._~+\/]+=*/i' => '$1 ' . self::MASK,
            // https://<network>.g.alchemy.com/v2/<key>
            '/(alchemy(?:api)?\.(?:com|io)\/v\d+\/)[A-Za-z0-9_\-]+/i' => '$1' . self::MASK,
            // https://<network>.infura.io/v3/<project id>
            '/(infura\.io\/v\d+\/)[A-Za-z0-9_\-]+/i' => '$1' . self::MASK,
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message) ?? $message;
        }

        foreach (self::configuredSecrets() as $secret) {
            $message = str_replace([$secret, rawurlencode($secret)], self::MASK, $message);
        }

        return $message;
    }

    /**
     * Redacted message of a throwable (convenience for log context and
     * persisted error fields).
     */
    public static function message(Throwable $e): string
    {
        return self::redact($e->getMessage());
    }

    /**
     * @return list<string>
     */
    private static function configuredSecrets(): array
    {
        $secrets = [];

        try {
            foreach (self::CONFIGURED_SECRET_KEYS as $key) {
                $value = config($key);

                if (is_string($value) && strlen($value) >= self::MIN_LITERAL_SECRET_LENGTH) {
                    $secrets[] = $value;
                }
            }
        } catch (Throwable) {
            // No container/config available (e.g. very early boot): the
            // pattern rules above still apply.
            return [];
        }

        // Longest first so a key that contains another is masked whole.
        usort($secrets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return array_values(array_unique($secrets));
    }
}
