<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Events\Broadcast\CardThreeDsCodeReady;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * 3-D Secure codes for Lepo cards (webhook card.3ds.otp).
 *
 * The code travels only one way to the phone: the authenticated
 * GET issued/{card}/3ds/code. Until then it is kept encrypted in the cache
 * until it expires (at most 10 minutes) and never logged or written to the
 * database. The push and the WS event only say that a code is waiting.
 */
final class LepoThreeDsCodes
{
    private const MAX_SECONDS = 600;

    public function __construct(private readonly PushNotificationService $push)
    {
    }

    /**
     * Keeps the code (called from the webhook request, before the payload
     * is queued without it).
     *
     * @param  array<mixed>  $data
     */
    public function store(array $data): void
    {
        $card = $this->cardFor($data);
        $code = $data['otp'] ?? null;
        if ($card === null || ! is_scalar($code) || (string) $code === '') {
            return;
        }

        $expires = $this->expiry($data);
        $seconds = min(self::MAX_SECONDS, (int) now()->diffInSeconds($expires, false));
        if ($seconds <= 0) {
            return;
        }

        $entry = [
            'code'       => (string) $code,
            'expires_at' => $expires->toIso8601String(),
            'merchant'   => is_string($data['merchantName'] ?? null) ? $data['merchantName'] : null,
            'amount'     => is_scalar($data['amount'] ?? null) ? (string) $data['amount'] : null,
            'currency'   => is_string($data['currency'] ?? null) ? $data['currency'] : null,
        ];

        Cache::put(self::key($card), Crypt::encryptString((string) json_encode($entry)), $seconds);
    }

    /**
     * Tells the user a code is waiting (called from the queued job; the data
     * no longer carries the code).
     *
     * @param  array<mixed>  $data
     */
    public function announce(array $data): void
    {
        $card = $this->cardFor($data);
        $user = $card?->user()->first();
        if ($card === null || ! $user instanceof User) {
            return;
        }

        $expiresAt = $this->expiry($data)->toIso8601String();
        CardThreeDsCodeReady::dispatch((int) $card->user_id, $card->id, $expiresAt);

        $this->push->sendToUser($user, 'cards.3ds.code', 'Card verification', 'Your card verification code is ready.', [
            'type'    => 'cards.3ds.code',
            'card_id' => $card->id,
        ]);
    }

    /**
     * @return array{code: string, expires_at: string, merchant: string|null, amount: string|null, currency: string|null}|null
     */
    public function current(Card $card): ?array
    {
        $raw = Cache::get(self::key($card));
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $entry = json_decode(Crypt::decryptString($raw), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($entry) || ! is_string($entry['code'] ?? null) || ! is_string($entry['expires_at'] ?? null)) {
            return null;
        }

        if (Carbon::parse($entry['expires_at'])->isPast()) {
            return null;
        }

        return [
            'code'       => $entry['code'],
            'expires_at' => $entry['expires_at'],
            'merchant'   => is_string($entry['merchant'] ?? null) ? $entry['merchant'] : null,
            'amount'     => is_string($entry['amount'] ?? null) ? $entry['amount'] : null,
            'currency'   => is_string($entry['currency'] ?? null) ? $entry['currency'] : null,
        ];
    }

    private static function key(Card $card): string
    {
        return "cards:3ds:{$card->id}";
    }

    /**
     * @param  array<mixed>  $data
     */
    private function cardFor(array $data): ?Card
    {
        $lepoCardId = $data['cardId'] ?? null;
        if (is_scalar($lepoCardId) && (string) $lepoCardId !== '') {
            $card = Card::query()->where('issuer', 'lepo')->where('issuer_card_token', (string) $lepoCardId)->first();
            if ($card !== null) {
                return $card;
            }
        }

        $ours = $data['externalCardId'] ?? null;

        return is_string($ours) && Str::isUuid($ours) ? Card::query()->whereKey($ours)->where('issuer', 'lepo')->first() : null;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function expiry(array $data): Carbon
    {
        $value = $data['expiresAt'] ?? null;
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                // fall through to the default lifetime
            }
        }

        return now()->addMinutes(5);
    }
}
