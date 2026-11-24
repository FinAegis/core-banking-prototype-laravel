<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Remembers the Privy identities of deleted accounts, so that a session from
 * before the deletion cannot open a new account.
 *
 * Erasure removes the Privy identity from the user row and asks Privy to
 * delete the user. A Privy token issued before that is still valid until it
 * expires, and an older app build signs in with it again right after the
 * deletion. Without this record, privy-login would find no account and open
 * a new one for the person who just deleted theirs.
 *
 * Only a keyed hash of the identity is stored, with the time of the deletion.
 * A token issued after the deletion is a new sign-in and may open a new
 * account (Privy also gives a new identity to a new sign-up).
 */
final class ErasedPrivyIdentities
{
    public const TABLE = 'erased_privy_identities';

    public static function record(string $privyUserId, ?DateTimeInterface $erasedAt = null): void
    {
        $now = Carbon::now();

        DB::table(self::TABLE)->upsert(
            [[
                'identity_hash' => self::hash($privyUserId),
                'erased_at'     => $erasedAt === null ? $now : Carbon::instance($erasedAt),
                'created_at'    => $now,
            ]],
            ['identity_hash'],
            ['erased_at']
        );
    }

    /**
     * Whether the account of this identity was deleted at or after the given
     * time, for example the time a token was issued.
     */
    public static function erasedSince(string $privyUserId, DateTimeInterface $issuedAt): bool
    {
        return DB::table(self::TABLE)
            ->where('identity_hash', self::hash($privyUserId))
            ->where('erased_at', '>=', Carbon::instance($issuedAt))
            ->exists();
    }

    private static function hash(string $privyUserId): string
    {
        return hash_hmac('sha256', 'erased-privy-identity:' . $privyUserId, (string) config('app.key'));
    }
}
