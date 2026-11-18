<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Services;

use App\Domain\CardIssuance\Events\Broadcast\CardStateChanged;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * FinCard card lifecycle: open a prefunded card, refresh its details, view
 * sensitive PAN/CVV (ephemeral — never persisted), freeze/unfreeze/cancel, move
 * funds between the account and the card, and apply card webhooks.
 *
 * The persistent Card row (issuer='fincard') maps the Zelta user ↔ FinCard
 * cardId and caches the card's spendable balance (FinCard is authoritative).
 * All amounts are integer minor units; FinCard expects/returns major-unit
 * decimal strings — converted here via bcmath (never float).
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §8
 */
final class FinCardCardService
{
    public function __construct(
        private readonly FinCardClient $client,
        private readonly FinCardAccountService $accounts,
    ) {
    }

    /**
     * @return Collection<int, Card>
     */
    public function listCards(User $user): Collection
    {
        return Card::query()
            ->where('user_id', $user->id)
            ->where('issuer', 'fincard')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findForUser(User $user, string $cardId): ?Card
    {
        return Card::query()
            ->where('user_id', $user->id)
            ->where('issuer', 'fincard')
            ->where('issuer_card_token', $cardId)
            ->first();
    }

    /**
     * Open a virtual card against the user's account + a verified cardholder,
     * loading `$amountCents` onto it. Persists the local mapping; the card's
     * final details (last4/network/expiry) settle via refresh/webhook.
     *
     * @param  array<string, mixed>  $context
     */
    public function openCard(User $user, Cardholder $cardholder, int $cardTypeId, int $amountCents, string $merchantOrderNo, array $context): Card
    {
        $account = $this->accounts->existingAccount($user);
        $accountId = $account instanceof FinCardAccount ? $account->fincard_account_id : '';

        $response = $this->client->openCard(
            $cardTypeId,
            $this->toMajor($amountCents),
            $accountId,
            (string) $cardholder->issuer_cardholder_id,
            $merchantOrderNo,
            $context,
        );
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        $card = new Card();
        $card->user_id = (string) $user->id;
        $card->cardholder_id = (string) $cardholder->id;
        $card->issuer_card_token = (string) ($data['cardId'] ?? $data['id'] ?? '');
        $card->issuer = 'fincard';
        $card->last4 = $this->extractLast4($data);
        $card->network = $this->mapNetwork((string) ($data['network'] ?? $data['cardBrand'] ?? 'visa'));
        $card->status = 'pending';
        $card->currency = 'USD';
        $card->balance_cents = $amountCents;
        $card->fincard_account_id = $accountId;
        $card->merchant_order_no = $merchantOrderNo;
        $card->save();

        return $card;
    }

    /**
     * Refresh a card's details + balance from FinCard.
     *
     * @param  array<string, mixed>  $context
     */
    public function refresh(Card $card, array $context = []): Card
    {
        $response = $this->client->getCardInfo($card->issuer_card_token, $context);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        if (($last4 = $this->extractLast4($data)) !== '0000') {
            $card->last4 = $last4;
        }
        if (isset($data['status'])) {
            $card->status = $this->mapStatus((string) $data['status']);
        }
        if (isset($data['balance']) && is_numeric((string) $data['balance'])) {
            $card->balance_cents = $this->toCents((string) $data['balance']);
        }
        $card->save();

        return $card;
    }

    /**
     * Ephemeral PAN/CVV/expiry — returned to the caller, never persisted.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function sensitiveDetails(Card $card, array $context = []): array
    {
        $response = $this->client->getCardSensitive($card->issuer_card_token, $context);

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function freeze(Card $card, string $merchantOrderNo, array $context = []): Card
    {
        $this->client->freezeCard($card->issuer_card_token, $merchantOrderNo, $context);
        $card->status = 'frozen';
        $card->frozen_at = now();
        $card->save();
        $this->broadcast($card);

        return $card;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function unfreeze(Card $card, string $merchantOrderNo, array $context = []): Card
    {
        $this->client->unfreezeCard($card->issuer_card_token, $merchantOrderNo, $context);
        $card->status = 'active';
        $card->frozen_at = null;
        $card->save();
        $this->broadcast($card);

        return $card;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function cancel(Card $card, string $merchantOrderNo, array $context = []): Card
    {
        $this->client->cancelCard($card->issuer_card_token, $merchantOrderNo, $context);
        $card->status = 'cancelled';
        $card->cancelled_at = now();
        $card->save();
        $this->broadcast($card);

        return $card;
    }

    /**
     * Move funds account → card (top up) and reflect the new balance.
     *
     * @param  array<string, mixed>  $context
     */
    public function topUp(Card $card, int $amountCents, string $merchantOrderNo, array $context = []): Card
    {
        $this->client->depositToCard($card->issuer_card_token, $this->toMajor($amountCents), $merchantOrderNo, $context);
        $card->balance_cents = (int) $card->balance_cents + $amountCents;
        $card->save();
        $this->broadcast($card);

        return $card;
    }

    /**
     * Move funds card → account (withdraw).
     *
     * @param  array<string, mixed>  $context
     */
    public function withdraw(Card $card, int $amountCents, string $merchantOrderNo, array $context = []): Card
    {
        $this->client->withdrawFromCard($card->issuer_card_token, $this->toMajor($amountCents), $merchantOrderNo, $context);
        $card->balance_cents = max(0, (int) $card->balance_cents - $amountCents);
        $card->save();
        $this->broadcast($card);

        return $card;
    }

    /**
     * Apply a FinCard card-operation webhook (create/freeze/unfreeze/cancel/
     * deposit/withdraw/blocked) to the local card state.
     *
     * @param  array<string, mixed>  $payload
     */
    public function applyCardWebhook(string $eventType, array $payload): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $cardId = (string) ($data['cardId'] ?? $data['id'] ?? '');
        if ($cardId === '') {
            Log::warning('FinCard card webhook without cardId', ['event' => $eventType]);

            return;
        }

        $card = Card::query()->where('issuer_card_token', $cardId)->where('issuer', 'fincard')->first();
        if (! $card instanceof Card) {
            Log::warning('FinCard card webhook for unknown card', ['card_id' => $cardId, 'event' => $eventType]);

            return;
        }

        $status = $this->mapCardEvent($eventType);
        if ($status !== null) {
            $card->status = $status;
            if ($status === 'frozen') {
                $card->frozen_at = now();
            } elseif ($status === 'cancelled') {
                $card->cancelled_at = now();
            }
        }
        if (isset($data['balance']) && is_numeric((string) $data['balance'])) {
            $card->balance_cents = $this->toCents((string) $data['balance']);
        }
        if (($last4 = $this->extractLast4($data)) !== '0000') {
            $card->last4 = $last4;
        }
        $card->save();

        $this->broadcast($card);
    }

    private function broadcast(Card $card): void
    {
        CardStateChanged::dispatch((int) $card->user_id, $card->issuer_card_token, $card->status, (int) $card->balance_cents);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractLast4(array $data): string
    {
        foreach (['last4', 'lastFour', 'cardLast4'] as $key) {
            $v = (string) ($data[$key] ?? '');
            if ($v !== '') {
                return substr($v, -4);
            }
        }
        $pan = (string) ($data['cardNo'] ?? $data['maskedPan'] ?? '');

        return $pan !== '' ? substr($pan, -4) : '0000';
    }

    private function mapNetwork(string $network): string
    {
        return match (strtolower($network)) {
            'mastercard', 'master' => 'mastercard',
            'discover'             => 'discover',
            default                => 'visa',
        };
    }

    private function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'normal', 'active'              => 'active',
            'freeze', 'frozen'              => 'frozen',
            'blocked'                       => 'frozen',
            'cancel', 'cancelled', 'closed' => 'cancelled',
            'expired'                       => 'expired',
            default                         => 'pending',
        };
    }

    private function mapCardEvent(string $eventType): ?string
    {
        return match ($eventType) {
            'create'            => 'active',
            'Freeze', 'blocked' => 'frozen',
            'UnFreeze'          => 'active',
            'cancel'            => 'cancelled',
            default             => null, // deposit/withdraw/overdraft_statement: balance only
        };
    }

    private function toMajor(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }

    private function toCents(string $amount): int
    {
        if (! is_numeric($amount)) {
            return 0;
        }

        return (int) bcmul(bcadd($amount, '0', 2), '100', 0);
    }
}
