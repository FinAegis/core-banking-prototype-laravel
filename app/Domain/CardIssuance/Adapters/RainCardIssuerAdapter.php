<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Adapters;

use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Enums\WalletType;
use App\Domain\CardIssuance\ValueObjects\CardTransaction;
use App\Domain\CardIssuance\ValueObjects\ProvisioningData;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;
use DateTimeImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Rain card issuer adapter using Rain's REST API.
 *
 * Rain is a modern card issuing platform for crypto/fintech companies.
 * Supports virtual card creation, spend controls, real-time authorizations,
 * and stablecoin-funded card programs.
 *
 * @see https://www.raincards.xyz/docs
 */
class RainCardIssuerAdapter implements CardIssuerInterface
{
    private readonly string $baseUrl;

    private readonly string $apiKey;

    private readonly string $programId;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.raincards.xyz/v1'), '/');
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->programId = (string) ($config['program_id'] ?? '');

        if ($this->apiKey === '' || $this->programId === '') {
            throw new RuntimeException(
                'Rain adapter requires api_key and program_id configuration.'
            );
        }
    }

    public function getName(): string
    {
        return 'rain';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createCard(
        string $userId,
        string $cardholderName,
        array $metadata = [],
        ?CardNetwork $network = null,
        ?string $label = null,
    ): VirtualCard {
        Log::info('Rain: Creating virtual card', [
            'user_id'         => $userId,
            'cardholder_name' => $cardholderName,
            'network'         => $network?->value,
            'label'           => $label,
        ]);

        $payload = [
            'cardholder_id' => $metadata['cardholder_id'] ?? $userId,
            'program_id'    => $this->programId,
            'type'          => 'virtual',
            'currency'      => $metadata['currency'] ?? 'USD',
        ];

        if ($label !== null) {
            $payload['nickname'] = $label;
        }

        if (isset($metadata['spend_limit_cents'])) {
            $payload['spending_limits'] = [
                [
                    'amount'   => (int) $metadata['spend_limit_cents'],
                    'interval' => $metadata['spend_limit_interval'] ?? 'monthly',
                ],
            ];
        }

        if ($network !== null) {
            $payload['network'] = strtolower($network->value);
        }

        $response = $this->request()->post('/cards', $payload);

        $this->assertSuccessful($response, 'Failed to create card');

        $data = $response->json('data') ?? $response->json();

        Log::info('Rain: Card created successfully', [
            'card_id' => $data['id'] ?? 'unknown',
            'user_id' => $userId,
        ]);

        return $this->mapResponseToVirtualCard($data, $cardholderName, $metadata, $label);
    }

    /**
     * @param  array<string>  $certificates
     */
    public function getProvisioningData(
        string $cardToken,
        WalletType $walletType,
        string $deviceId,
        array $certificates = [],
    ): ProvisioningData {
        Log::info('Rain: Requesting provisioning data', [
            'card_token'  => $cardToken,
            'wallet_type' => $walletType->value,
            'device_id'   => $deviceId,
        ]);

        $payload = [
            'card_id'     => $cardToken,
            'wallet_type' => $walletType === WalletType::APPLE_PAY ? 'apple_pay' : 'google_pay',
            'device_id'   => $deviceId,
        ];

        if ($certificates !== []) {
            $payload['certificates'] = $certificates;
        }

        $response = $this->request()->post('/cards/' . $cardToken . '/provision', $payload);

        $this->assertSuccessful($response, 'Failed to get provisioning data');

        $data = $response->json('data') ?? $response->json();

        return new ProvisioningData(
            cardId: $cardToken,
            walletType: $walletType,
            encryptedPassData: (string) ($data['encrypted_pass_data'] ?? ''),
            activationData: (string) ($data['activation_data'] ?? ''),
            ephemeralPublicKey: (string) ($data['ephemeral_public_key'] ?? ''),
            certificateChain: (array) ($data['certificate_chain'] ?? []),
        );
    }

    public function freezeCard(string $cardToken): bool
    {
        Log::info('Rain: Freezing card', ['card_token' => $cardToken]);

        return $this->updateCardStatus($cardToken, 'frozen');
    }

    public function unfreezeCard(string $cardToken): bool
    {
        Log::info('Rain: Unfreezing card', ['card_token' => $cardToken]);

        return $this->updateCardStatus($cardToken, 'active');
    }

    public function cancelCard(string $cardToken, string $reason): bool
    {
        Log::info('Rain: Cancelling card', [
            'card_token' => $cardToken,
            'reason'     => $reason,
        ]);

        $response = $this->request()->post('/cards/' . $cardToken . '/cancel', [
            'reason' => $reason,
        ]);

        if (! $response->successful()) {
            Log::error('Rain: Card cancellation failed', [
                'card_token' => $cardToken,
                'status'     => $response->status(),
                'body'       => $response->json(),
            ]);

            return false;
        }

        return true;
    }

    public function getCard(string $cardToken): ?VirtualCard
    {
        Log::info('Rain: Retrieving card details', ['card_token' => $cardToken]);

        $response = $this->request()->get('/cards/' . $cardToken);

        if ($response->status() === 404) {
            return null;
        }

        $this->assertSuccessful($response, 'Failed to retrieve card');

        $data = $response->json('data') ?? $response->json();

        return $this->mapResponseToVirtualCard($data);
    }

    /**
     * @return array<VirtualCard>
     */
    public function listUserCards(string $userId): array
    {
        Log::info('Rain: Listing user cards', ['user_id' => $userId]);

        $response = $this->request()->get('/cards', [
            'cardholder_id' => $userId,
            'program_id'    => $this->programId,
        ]);

        if (! $response->successful()) {
            Log::warning('Rain: Failed to list user cards', [
                'user_id' => $userId,
                'status'  => $response->status(),
            ]);

            return [];
        }

        $items = $response->json('data') ?? [];
        $cards = [];
        foreach ($items as $item) {
            $card = $this->mapResponseToVirtualCard($item);
            if ($card->status !== CardStatus::CANCELLED) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * @return array{transactions: array<CardTransaction>, next_cursor: string|null}
     */
    public function getTransactions(string $cardToken, int $limit = 20, ?string $cursor = null): array
    {
        $query = [
            'card_id' => $cardToken,
            'limit'   => min($limit, 100),
        ];

        if ($cursor !== null) {
            $query['starting_after'] = $cursor;
        }

        $response = $this->request()->get('/transactions', $query);

        $this->assertSuccessful($response, 'getTransactions');

        $data = $response->json();
        $items = (array) ($data['data'] ?? []);
        $hasMore = (bool) ($data['has_more'] ?? false);

        $transactions = array_map(
            fn (array $tx) => $this->mapTransactionResponse($tx, $cardToken),
            $items,
        );

        $lastId = $items !== [] ? (string) (end($items)['id'] ?? '') : null;
        $nextCursor = $hasMore ? $lastId : null;

        return [
            'transactions' => $transactions,
            'next_cursor'  => $nextCursor,
        ];
    }

    private function updateCardStatus(string $cardToken, string $status): bool
    {
        $response = $this->request()->patch('/cards/' . $cardToken, [
            'status' => $status,
        ]);

        if (! $response->successful()) {
            Log::error('Rain: Card status update failed', [
                'card_token'    => $cardToken,
                'target_status' => $status,
                'status'        => $response->status(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $extraMetadata
     */
    private function mapResponseToVirtualCard(
        array $data,
        ?string $cardholderName = null,
        array $extraMetadata = [],
        ?string $label = null,
    ): VirtualCard {
        $token = (string) ($data['id'] ?? '');
        $last4 = (string) ($data['last_four'] ?? $data['last4'] ?? '');
        $pan = isset($data['pan']) ? (string) $data['pan'] : null;
        $cvv = isset($data['cvv']) ? (string) $data['cvv'] : null;

        $resolvedName = $cardholderName ?? (string) ($data['cardholder_name'] ?? 'Unknown');
        $network = $this->mapCardNetwork((string) ($data['network'] ?? ''));
        $status = $this->mapCardStatus((string) ($data['status'] ?? 'pending'));
        $expiresAt = $this->parseExpiration($data);

        $metadata = array_merge($extraMetadata, ['rain_card_id' => $token]);
        $resolvedLabel = $label ?? ($data['nickname'] ?? null);

        return new VirtualCard(
            cardToken: $token,
            last4: $last4,
            network: $network,
            status: $status,
            cardholderName: $resolvedName,
            expiresAt: $expiresAt,
            pan: $pan,
            cvv: $cvv,
            metadata: $metadata,
            label: is_string($resolvedLabel) ? $resolvedLabel : null,
        );
    }

    private function mapCardStatus(string $rainStatus): CardStatus
    {
        return match (strtolower($rainStatus)) {
            'active'                  => CardStatus::ACTIVE,
            'frozen', 'locked'        => CardStatus::FROZEN,
            'cancelled', 'terminated' => CardStatus::CANCELLED,
            'expired'                 => CardStatus::EXPIRED,
            default                   => CardStatus::PENDING,
        };
    }

    private function mapCardNetwork(string $network): CardNetwork
    {
        return match (true) {
            str_contains(strtolower($network), 'mastercard') => CardNetwork::MASTERCARD,
            default                                          => CardNetwork::VISA,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parseExpiration(array $data): DateTimeImmutable
    {
        if (isset($data['expiration_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', (string) $data['expiration_date']);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        if (isset($data['exp_month'], $data['exp_year'])) {
            $month = str_pad((string) $data['exp_month'], 2, '0', STR_PAD_LEFT);
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $data['exp_year'] . '-' . $month . '-01');
            if ($parsed !== false) {
                return $parsed;
            }
        }

        return (new DateTimeImmutable())->modify('+3 years');
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    private function mapTransactionResponse(array $tx, string $cardToken): CardTransaction
    {
        $statusMap = [
            'pending'  => 'pending',
            'settled'  => 'settled',
            'declined' => 'declined',
            'reversed' => 'declined',
        ];

        $rainStatus = strtolower((string) ($tx['status'] ?? 'pending'));
        $status = $statusMap[$rainStatus] ?? 'pending';

        return new CardTransaction(
            transactionId: (string) ($tx['id'] ?? ''),
            cardToken: $cardToken,
            merchantName: (string) ($tx['merchant_name'] ?? $tx['merchant']['name'] ?? 'Unknown'),
            merchantCategory: (string) ($tx['merchant_category_code'] ?? $tx['merchant']['mcc'] ?? ''),
            amountCents: (int) ($tx['amount'] ?? 0),
            currency: (string) ($tx['currency'] ?? 'USD'),
            status: $status,
            timestamp: new DateTimeImmutable((string) ($tx['created_at'] ?? 'now')),
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);
    }

    private function assertSuccessful(Response $response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        $body = $response->json();
        $errorCode = (string) ($body['error']['code'] ?? 'UNKNOWN');
        $errorMessage = (string) ($body['error']['message'] ?? $response->body());

        Log::error("Rain: {$context}", [
            'status'        => $response->status(),
            'error_code'    => $errorCode,
            'error_message' => $errorMessage,
        ]);

        throw new RuntimeException(
            "Rain API error ({$errorCode}): {$errorMessage} — {$context}"
        );
    }
}
