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
 * Marqeta card issuer adapter using Marqeta REST API v3.
 *
 * Handles virtual card lifecycle management and digital wallet provisioning
 * through Marqeta's platform.
 */
class MarqetaCardIssuerAdapter implements CardIssuerInterface
{
    private readonly string $baseUrl;

    private readonly string $applicationToken;

    private readonly string $adminAccessToken;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
    ) {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->applicationToken = (string) ($config['application_token'] ?? '');
        $this->adminAccessToken = (string) ($config['admin_access_token'] ?? '');

        if ($this->baseUrl === '' || $this->applicationToken === '' || $this->adminAccessToken === '') {
            throw new RuntimeException(
                'Marqeta adapter requires base_url, application_token, and admin_access_token configuration.'
            );
        }
    }

    public function getName(): string
    {
        return 'marqeta';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function createCard(
        string $userId,
        string $cardholderName,
        array $metadata = [],
        ?CardNetwork $network = null,
        ?string $label = null,
    ): VirtualCard {
        Log::info('Marqeta: Creating virtual card', [
            'user_id'         => $userId,
            'cardholder_name' => $cardholderName,
            'network'         => $network?->value,
            'label'           => $label,
        ]);

        $payload = [
            'user_token'         => $userId,
            'card_product_token' => $metadata['card_product_token'] ?? $this->config['card_product_token'] ?? 'default',
        ];

        if ($label !== null) {
            $payload['metadata'] = array_merge($metadata, ['label' => $label]);
        } elseif ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->request()->post('/cards', $payload);

        $this->assertSuccessful($response, 'Failed to create card');

        $data = $response->json();

        Log::info('Marqeta: Card created successfully', [
            'card_token' => $data['token'] ?? 'unknown',
            'user_id'    => $userId,
        ]);

        return $this->mapResponseToVirtualCard($data, $cardholderName, $metadata, $label);
    }

    /**
     * @param array<string> $certificates
     */
    public function getProvisioningData(
        string $cardToken,
        WalletType $walletType,
        string $deviceId,
        array $certificates = [],
    ): ProvisioningData {
        Log::info('Marqeta: Requesting provisioning data', [
            'card_token'  => $cardToken,
            'wallet_type' => $walletType->value,
            'device_id'   => $deviceId,
        ]);

        $endpoint = match ($walletType) {
            WalletType::APPLE_PAY  => '/digitalwallettokens/applepay',
            WalletType::GOOGLE_PAY => '/digitalwallettokens/androidpay',
        };

        $payload = [
            'card_token'               => $cardToken,
            'device_id'                => $deviceId,
            'device_type'              => $walletType === WalletType::APPLE_PAY ? 'MOBILE_PHONE' : 'MOBILE_PHONE',
            'provisioning_app_version' => '1.0',
        ];

        if ($certificates !== []) {
            $payload['certificates'] = $certificates;
        }

        $response = $this->request()->post($endpoint, $payload);

        $this->assertSuccessful($response, 'Failed to get provisioning data');

        $data = $response->json();

        Log::info('Marqeta: Provisioning data retrieved', [
            'card_token'  => $cardToken,
            'wallet_type' => $walletType->value,
        ]);

        return new ProvisioningData(
            cardId: $cardToken,
            walletType: $walletType,
            encryptedPassData: (string) ($data['encrypted_pass_data'] ?? $data['card_data'] ?? ''),
            activationData: (string) ($data['activation_data'] ?? ''),
            ephemeralPublicKey: (string) ($data['ephemeral_public_key'] ?? ''),
            certificateChain: (array) ($data['certificate_chain'] ?? $data['certificates'] ?? []),
        );
    }

    public function freezeCard(string $cardToken): bool
    {
        Log::info('Marqeta: Freezing card', ['card_token' => $cardToken]);

        return $this->transitionCardState($cardToken, 'SUSPENDED', 'FROZEN_BY_USER', 'User requested freeze');
    }

    public function unfreezeCard(string $cardToken): bool
    {
        Log::info('Marqeta: Unfreezing card', ['card_token' => $cardToken]);

        return $this->transitionCardState($cardToken, 'ACTIVE', 'UNSUSPENDED', 'User requested unfreeze');
    }

    public function cancelCard(string $cardToken, string $reason): bool
    {
        Log::info('Marqeta: Cancelling card', [
            'card_token' => $cardToken,
            'reason'     => $reason,
        ]);

        return $this->transitionCardState($cardToken, 'TERMINATED', 'TERMINATED', $reason);
    }

    public function getCard(string $cardToken): ?VirtualCard
    {
        Log::info('Marqeta: Retrieving card details', ['card_token' => $cardToken]);

        $response = $this->request()->get("/cards/{$cardToken}");

        if ($response->status() === 404) {
            Log::warning('Marqeta: Card not found', ['card_token' => $cardToken]);

            return null;
        }

        $this->assertSuccessful($response, 'Failed to retrieve card');

        $data = $response->json();

        Log::info('Marqeta: Card retrieved successfully', [
            'card_token' => $cardToken,
            'state'      => $data['state'] ?? 'unknown',
        ]);

        return $this->mapResponseToVirtualCard($data);
    }

    /**
     * @return array<VirtualCard>
     */
    public function listUserCards(string $userId): array
    {
        Log::info('Marqeta: Listing user cards', ['user_id' => $userId]);

        $response = $this->request()->get('/cards/user/' . $userId);

        if (! $response->successful()) {
            Log::warning('Marqeta: Failed to list user cards', [
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
     * Transition a card to a new state via the Marqeta transitions API.
     */
    private function transitionCardState(
        string $cardToken,
        string $state,
        string $reasonCode,
        string $reason,
    ): bool {
        $payload = [
            'card_token'  => $cardToken,
            'state'       => $state,
            'reason_code' => $reasonCode,
            'reason'      => $reason,
            'channel'     => 'API',
        ];

        $response = $this->request()->put("/cards/{$cardToken}/transitions", $payload);

        if (! $response->successful()) {
            Log::error('Marqeta: Card state transition failed', [
                'card_token'   => $cardToken,
                'target_state' => $state,
                'status'       => $response->status(),
                'body'         => $response->json(),
            ]);

            return false;
        }

        Log::info('Marqeta: Card state transition successful', [
            'card_token' => $cardToken,
            'new_state'  => $state,
        ]);

        return true;
    }

    /**
     * Map a Marqeta API card response to our VirtualCard value object.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $extraMetadata
     */
    private function mapResponseToVirtualCard(
        array $data,
        ?string $cardholderName = null,
        array $extraMetadata = [],
        ?string $label = null,
    ): VirtualCard {
        $token = (string) ($data['token'] ?? '');
        $last4 = (string) ($data['last_four'] ?? '');
        $pan = isset($data['pan']) ? (string) $data['pan'] : null;
        $cvv = isset($data['cvv_number']) ? (string) $data['cvv_number'] : null;

        // Resolve cardholder name from response or argument
        $resolvedCardholderName = $cardholderName
            ?? (string) ($data['fulfillment']['card_personalization']['text']['name_line_1']['value'] ?? 'Unknown');

        // Map Marqeta card brand to our CardNetwork enum
        $network = $this->mapCardNetwork($data['card_product_token'] ?? '', $data);

        // Map Marqeta state to our CardStatus enum
        $status = $this->mapCardStatus((string) ($data['state'] ?? 'UNACTIVATED'));

        // Parse expiration date
        $expiresAt = $this->parseExpiration($data);

        // Merge metadata
        $metadata = array_merge(
            $extraMetadata,
            (array) ($data['metadata'] ?? []),
            ['marqeta_token' => $token],
        );

        // Resolve label
        $resolvedLabel = $label ?? ($data['metadata']['label'] ?? null);

        return new VirtualCard(
            cardToken: $token,
            last4: $last4,
            network: $network,
            status: $status,
            cardholderName: $resolvedCardholderName,
            expiresAt: $expiresAt,
            pan: $pan,
            cvv: $cvv,
            metadata: $metadata,
            label: $resolvedLabel,
        );
    }

    /**
     * Map Marqeta card state string to our CardStatus enum.
     */
    private function mapCardStatus(string $marqetaState): CardStatus
    {
        return match (strtoupper($marqetaState)) {
            'ACTIVE'      => CardStatus::ACTIVE,
            'SUSPENDED'   => CardStatus::FROZEN,
            'TERMINATED'  => CardStatus::CANCELLED,
            'UNACTIVATED' => CardStatus::PENDING,
            default       => CardStatus::PENDING,
        };
    }

    /**
     * Map Marqeta card brand to our CardNetwork enum.
     *
     * @param array<string, mixed> $data
     */
    private function mapCardNetwork(string $cardProductToken, array $data): CardNetwork
    {
        // Marqeta may return the network in card_product or directly
        $brand = strtolower((string) ($data['card_brand'] ?? $data['network'] ?? ''));

        return match (true) {
            str_contains($brand, 'mastercard') => CardNetwork::MASTERCARD,
            str_contains($brand, 'visa')       => CardNetwork::VISA,
            default                            => CardNetwork::VISA,
        };
    }

    /**
     * Parse expiration date from Marqeta response.
     *
     * @param array<string, mixed> $data
     */
    private function parseExpiration(array $data): DateTimeImmutable
    {
        if (isset($data['expiration_time'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', (string) $data['expiration_time'])
                ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', (string) $data['expiration_time']);

            if ($parsed !== false) {
                return $parsed;
            }
        }

        if (isset($data['expiration'])) {
            $parsed = DateTimeImmutable::createFromFormat('my', (string) $data['expiration']);

            if ($parsed !== false) {
                return $parsed;
            }
        }

        // Fallback: 3 years from now
        return (new DateTimeImmutable())->modify('+3 years');
    }

    /**
     * Build an authenticated HTTP client for the Marqeta API.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->applicationToken, $this->adminAccessToken)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);
    }

    /**
     * Assert that a Marqeta API response is successful, throw otherwise.
     */
    private function assertSuccessful(Response $response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        $body = $response->json();
        $errorCode = (string) ($body['error_code'] ?? 'UNKNOWN');
        $errorMessage = (string) ($body['error_message'] ?? $response->body());

        Log::error("Marqeta: {$context}", [
            'status'        => $response->status(),
            'error_code'    => $errorCode,
            'error_message' => $errorMessage,
            'body'          => $body,
        ]);

        throw new RuntimeException(
            "Marqeta API error ({$errorCode}): {$errorMessage} — {$context}"
        );
    }

    /**
     * Get transaction history for a card from Marqeta.
     *
     * @return array{transactions: array<CardTransaction>, next_cursor: string|null}
     */
    public function getTransactions(string $cardToken, int $limit = 20, ?string $cursor = null): array
    {
        $query = [
            'card_token' => $cardToken,
            'count'      => min($limit, 100),
            'sort_by'    => '-created_time',
        ];

        if ($cursor !== null) {
            $query['start_index'] = (int) $cursor;
        }

        $response = $this->request()->get('/transactions', $query);

        $this->assertSuccessful($response, 'getTransactions');

        $data = $response->json();

        $transactions = array_map(
            fn (array $tx) => $this->mapTransactionResponse($tx, $cardToken),
            (array) ($data['data'] ?? []),
        );

        $endIndex = $data['end_index'] ?? null;
        $isMore = $data['is_more'] ?? false;
        $nextCursor = ($isMore && $endIndex !== null) ? (string) ((int) $endIndex + 1) : null;

        return [
            'transactions' => $transactions,
            'next_cursor'  => $nextCursor,
        ];
    }

    /**
     * Map a Marqeta transaction response to a CardTransaction value object.
     *
     * @param array<string, mixed> $tx
     */
    private function mapTransactionResponse(array $tx, string $cardToken): CardTransaction
    {
        $acceptor = (array) ($tx['card_acceptor'] ?? []);

        // Map Marqeta state to our status
        $state = (string) ($tx['state'] ?? 'PENDING');
        $statusMap = [
            'PENDING'    => 'pending',
            'COMPLETION' => 'settled',
            'CLEARED'    => 'settled',
            'DECLINED'   => 'declined',
        ];
        $status = $statusMap[$state] ?? 'pending';

        // Convert amount: Marqeta returns float, we store cents
        $amount = (float) ($tx['amount'] ?? 0);
        $amountCents = (int) round($amount * 100);

        return new CardTransaction(
            transactionId: (string) ($tx['token'] ?? ''),
            cardToken: $cardToken,
            merchantName: (string) ($acceptor['name'] ?? 'Unknown'),
            merchantCategory: (string) ($acceptor['mcc'] ?? ''),
            amountCents: $amountCents,
            currency: (string) ($tx['currency_code'] ?? 'USD'),
            status: $status,
            timestamp: new DateTimeImmutable((string) ($tx['created_time'] ?? 'now')),
        );
    }
}
