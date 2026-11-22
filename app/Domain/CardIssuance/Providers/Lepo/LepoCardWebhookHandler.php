<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lepo card webhooks (card.created, card.activated, card.updated,
 * card.deleted, card.state_updated). The payloads carry Lepo's card id and
 * sometimes our card id, so the handler finds the card and re-reads it from
 * Lepo; a deleted card may no longer be readable and is marked cancelled.
 */
final class LepoCardWebhookHandler
{
    public const TYPES = ['card.created', 'card.activated', 'card.updated', 'card.deleted', 'card.state_updated'];

    public function __construct(private readonly LepoCardLifecycle $cards)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, string $eventId, string $type): void
    {
        $lepoCardId = $this->text($data['cardId'] ?? $data['id'] ?? null);

        $card = $lepoCardId === null ? null : Card::query()->where('issuer', 'lepo')->where('issuer_card_token', $lepoCardId)->first();
        $card ??= $this->byOurId($data['externalCardId'] ?? null);
        if ($card === null && $type === 'card.created' && $lepoCardId !== null) {
            $card = $this->waitingCardOf($data['userId'] ?? null);
        }

        if ($card === null) {
            Log::info('Lepo card webhook for an unknown card ignored', ['event_id' => $eventId, 'type' => $type]);

            return;
        }

        if (! $card->isOpenAtProvider() && $lepoCardId !== null) {
            $card->forceFill(['issuer_card_token' => $lepoCardId])->save();
        }

        if ($type === 'card.deleted') {
            $this->cards->sync($card, ['status' => 'CANCELLED']);

            return;
        }

        if ($card->isOpenAtProvider()) {
            $this->cards->refresh($card);
        }
    }

    private function byOurId(mixed $id): ?Card
    {
        return is_string($id) && Str::isUuid($id) ? Card::query()->whereKey($id)->where('issuer', 'lepo')->first() : null;
    }

    /**
     * A card Lepo created without our id: the one card of this Lepo user that
     * is still waiting to be opened (one card per user keeps it unambiguous).
     */
    private function waitingCardOf(mixed $lepoUserId): ?Card
    {
        $userId = $this->text($lepoUserId);
        $cardholder = $userId === null ? null : Cardholder::query()->where('provider', 'lepo')->where('issuer_cardholder_id', $userId)->first();
        if ($cardholder === null) {
            return null;
        }

        $waiting = Card::query()
            ->where('cardholder_id', $cardholder->id)
            ->where('issuer', 'lepo')
            ->whereNull('issuer_card_token')
            ->where('status', Card::STATUS_PENDING)
            ->get();

        return $waiting->count() === 1 ? $waiting->first() : null;
    }

    private function text(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
