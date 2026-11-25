<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo\Withdrawals;

use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Models\CardWithdrawalEvent;

/**
 * Writes the append-only audit trail of a withdrawal. Callers pass short
 * notes of their own; never the code, the token or a provider payload.
 */
final class WithdrawalJournal
{
    public const ACTOR_JOB = 'job';

    private function __construct()
    {
    }

    public static function user(int $userId): string
    {
        return "user:{$userId}";
    }

    public static function operator(string $email): string
    {
        return 'operator:' . mb_substr($email, 0, 200);
    }

    /**
     * @param  array{partner_http_status?: int|null, from_status?: string|null, to_status?: string|null, note?: string|null, ip_address?: string|null, user_agent?: string|null}  $details
     */
    public static function record(CardWithdrawal $withdrawal, string $actor, string $action, array $details = []): void
    {
        $event = new CardWithdrawalEvent();
        $event->forceFill([
            'withdrawal_id'       => $withdrawal->id,
            'at'                  => now(),
            'actor'               => mb_substr($actor, 0, 255),
            'action'              => $action,
            'partner_http_status' => isset($details['partner_http_status']) && $details['partner_http_status'] > 0 ? $details['partner_http_status'] : null,
            'from_status'         => $details['from_status'] ?? null,
            'to_status'           => $details['to_status'] ?? null,
            'note'                => isset($details['note']) ? mb_substr($details['note'], 0, 255) : null,
            'ip_address'          => isset($details['ip_address']) ? mb_substr($details['ip_address'], 0, 45) : null,
            'user_agent'          => isset($details['user_agent']) ? mb_substr($details['user_agent'], 0, 255) : null,
        ])->save();
    }
}
