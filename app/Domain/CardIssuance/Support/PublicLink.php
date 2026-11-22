<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Support;

/**
 * Links the app opens (identity check, card details): APP_URL, not the API
 * request's host, and always https in production — the app refuses any other
 * scheme. Pair with path-only signatures so the link stays valid behind a
 * proxy.
 */
final class PublicLink
{
    public static function to(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if (app()->environment('production')) {
            $base = (string) preg_replace('#^http://#i', 'https://', $base);
        }

        return $base . '/' . ltrim($path, '/');
    }
}
