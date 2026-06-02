<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Kyc\Providers;

use App\Domain\Compliance\Kyc\Contracts\KycProviderInterface;
use App\Domain\Compliance\Kyc\Enums\KycPurpose;
use App\Domain\Compliance\Kyc\Exceptions\UnsupportedKycPurposeException;
use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Infrastructure\Bridge\BridgeClient;
use App\Infrastructure\Bridge\BridgeWebhookVerifier;
use App\Models\User;
use Carbon\Carbon;
use RuntimeException;

/**
 * Bridge.xyz adapter.
 *
 * Handles RAMP + CARDS purposes (same Bridge customer record under the
 * hood). TRUSTCERT routes to OndatoKycProvider per config('kyc.routing').
 *
 * Lazy customer provisioning: first call to `getHostedLink` creates the
 * Bridge customer + persists the `bridge_customers` row, then requests a
 * hosted KYC link. Idempotency-Key prevents duplicate Bridge customers
 * if the local INSERT fails after the remote call.
 *
 * @see docs/BACKEND_HANDOVER_BRIDGE_RAMP.md §3.1, §3.3
 * @see docs/adr/0005-bridge-xyz-over-stripe-crypto-onramp.md
 * @see docs/adr/0006-bridge-developer-fees-as-markup-mechanism.md
 */
final class BridgeKycProvider implements KycProviderInterface
{
    public function __construct(
        private readonly BridgeClient $client,
        private readonly BridgeWebhookVerifier $verifier,
    ) {
    }

    public function getName(): string
    {
        return 'bridge';
    }

    public function getHostedLink(int $userId, KycPurpose $purpose, array $context = []): string
    {
        if ($purpose !== KycPurpose::RAMP && $purpose !== KycPurpose::CARDS) {
            throw UnsupportedKycPurposeException::for($this->getName(), $purpose);
        }

        /** @var User $user */
        $user = User::findOrFail($userId);
        $customer = $this->findOrCreateBridgeCustomer($user);

        // Reuse a still-valid link to avoid creating duplicate links.
        if (
            $customer->kyc_link_url !== null
            && $customer->kyc_link_url !== ''
            && $customer->kyc_link_expires_at !== null
            && $customer->kyc_link_expires_at->isFuture()
        ) {
            return $customer->kyc_link_url;
        }

        $response = $this->client->createKycLink(
            $customer->bridge_customer_id,
            [
                'type'         => 'individual',
                'full_name'    => (string) ($user->name ?? ''),
                'email'        => (string) ($user->email ?? ''),
                'redirect_uri' => (string) config('kyc.providers.bridge.kyc_redirect_uri', ''),
            ],
            idempotencyKey: 'bridge_kyc_link:' . $user->id . ':' . now()->timestamp,
        );

        $url = (string) ($response['url'] ?? $response['link'] ?? '');
        if ($url === '') {
            throw new RuntimeException('Bridge createKycLink did not return a hosted link URL.');
        }

        $expiresAt = isset($response['expires_at'])
            ? Carbon::parse((string) $response['expires_at'])
            : null;

        $customer->update([
            'kyc_link_url'        => $url,
            'kyc_link_expires_at' => $expiresAt,
            'kyc_status'          => $customer->kyc_status === BridgeCustomer::KYC_NOT_STARTED
                ? BridgeCustomer::KYC_PENDING
                : $customer->kyc_status,
        ]);

        return $url;
    }

    private function findOrCreateBridgeCustomer(User $user): BridgeCustomer
    {
        $existing = BridgeCustomer::where('user_id', $user->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $defaultFeeBps = (int) config(
            'kyc.providers.bridge.default_developer_fee_bps',
            BridgeCustomer::DEV_FEE_BPS_FREE,
        );

        $response = $this->client->createCustomer(
            [
                'type'              => 'individual',
                'full_name'         => (string) ($user->name ?? ''),
                'email'             => (string) ($user->email ?? ''),
                'developer_fee_bps' => $defaultFeeBps,
            ],
            idempotencyKey: 'bridge_customer:' . $user->id,
        );

        $bridgeCustomerId = (string) ($response['id'] ?? '');
        if ($bridgeCustomerId === '') {
            throw new RuntimeException('Bridge createCustomer did not return an id.');
        }

        return BridgeCustomer::create([
            'user_id'            => $user->id,
            'bridge_customer_id' => $bridgeCustomerId,
            'kyc_status'         => BridgeCustomer::KYC_NOT_STARTED,
            'developer_fee_bps'  => $defaultFeeBps,
        ]);
    }

    public function getStatus(int $userId): array
    {
        $customer = BridgeCustomer::where('user_id', $userId)->first();

        if ($customer === null) {
            return [
                'status'   => 'not_started',
                'metadata' => [],
            ];
        }

        $status = match ($customer->kyc_status) {
            BridgeCustomer::KYC_APPROVED => 'approved',
            BridgeCustomer::KYC_PENDING  => 'pending',
            BridgeCustomer::KYC_REJECTED => 'rejected',
            default                      => 'not_started',
        };

        return [
            'status'   => $status,
            'metadata' => [
                'bridge_customer_id'    => $customer->bridge_customer_id,
                'virtual_account_ready' => $customer->hasVirtualAccount(),
                'supported_rails'       => $customer->supported_rails ?? [],
            ],
        ];
    }

    public function getWebhookValidator(): callable
    {
        return fn (string $rawBody, string $signatureHeader): bool => $this->verifier->verify(
            $rawBody,
            $signatureHeader,
        );
    }

    public function getWebhookSignatureHeader(): string
    {
        return 'X-Webhook-Signature';
    }

    public function normalizeWebhookPayload(array $payload): ?array
    {
        $type = (string) ($payload['type'] ?? '');

        $status = match ($type) {
            'customer.kyc_link_completed' => 'approved',
            'customer.kyc_link_rejected'  => 'rejected',
            default                       => null,
        };

        if ($status === null) {
            return null;
        }

        $object = $payload['data']['object'] ?? $payload['data'] ?? null;
        if (! is_array($object)) {
            return null;
        }

        $bridgeCustomerId = (string) ($object['customer_id'] ?? $object['id'] ?? '');
        $userId = null;
        if ($bridgeCustomerId !== '') {
            $userId = BridgeCustomer::where('bridge_customer_id', $bridgeCustomerId)->value('user_id');
        }

        return [
            'user_id'    => $userId !== null ? (int) $userId : null,
            'event_type' => $type,
            'status'     => $status,
            'raw'        => $object,
        ];
    }
}
