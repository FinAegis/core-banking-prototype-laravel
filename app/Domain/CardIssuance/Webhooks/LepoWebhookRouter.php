<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Webhooks;

use App\Infrastructure\Lepo\LepoRedactor;
use Illuminate\Support\Facades\Log;

/**
 * Routes verified, deduplicated Lepo webhook events to their handlers.
 *
 * Handlers per event type arrive with the onboarding, card and top-up work;
 * until then every event is acknowledged and logged by id and type only (never
 * its data, which can carry personal data or a 3-D Secure code).
 */
final class LepoWebhookRouter
{
    /** Event types whose payload carries a one-time 3-D Secure code. */
    private const SECURE_CODE_TYPES = ['card.3ds.otp', 'card.3ds_auth_request'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function route(string $type, array $data, string $eventId): void
    {
        Log::info('Lepo webhook received', ['event_id' => $eventId, 'type' => $type]);
    }

    /**
     * Prepares a payload for the queue: card secrets are removed from every
     * event type, and one-time codes from 3-D Secure events.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function redact(string $type, array $data): array
    {
        /** @var array<string, mixed> $data */
        $data = LepoRedactor::cardSecrets($data);

        if (! in_array($type, self::SECURE_CODE_TYPES, true)) {
            return $data;
        }

        /** @var array<string, mixed> $withoutCodes */
        $withoutCodes = self::withoutCodes($data);

        return $withoutCodes;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function withoutCodes(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/otp|code|passcode/i', $key) === 1) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::withoutCodes($value);
            }
        }

        return $data;
    }
}
