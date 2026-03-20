<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Listeners;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\VisaCli\Events\VisaCliCardEnrolled;
use Illuminate\Support\Facades\Log;

/**
 * Syncs a newly enrolled Visa CLI card to the CardIssuance domain.
 *
 * Creates a read-only Card record with metadata.source = 'visa_cli'
 * to maintain a unified card view across the platform.
 */
class SyncVisaCliCardToCardIssuance
{
    public function handle(VisaCliCardEnrolled $event): void
    {
        // Check if already synced
        $existing = Card::where('issuer_card_token', $event->cardIdentifier)
            ->where('issuer', 'visa_cli')
            ->first();

        if ($existing !== null) {
            Log::debug('Visa CLI card already synced to CardIssuance', [
                'card_identifier' => $event->cardIdentifier,
            ]);

            return;
        }

        Card::create([
            'user_id'           => $event->userId,
            'cardholder_id'     => $event->userId,
            'issuer_card_token' => $event->cardIdentifier,
            'issuer'            => 'visa_cli',
            'last4'             => $event->last4,
            'network'           => $event->network,
            'status'            => 'active',
            'currency'          => 'USD',
            'label'             => 'Visa CLI Card',
            'metadata'          => array_merge($event->metadata, [
                'source'    => 'visa_cli',
                'synced_at' => now()->toIso8601String(),
            ]),
        ]);

        Log::info('Visa CLI card synced to CardIssuance', [
            'user_id'         => $event->userId,
            'card_identifier' => $event->cardIdentifier,
            'last4'           => $event->last4,
        ]);
    }
}
