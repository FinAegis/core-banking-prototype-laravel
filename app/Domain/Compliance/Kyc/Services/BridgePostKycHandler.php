<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Kyc\Services;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Compliance\Kyc\Events\Broadcast\BridgeKycCompleted;
use App\Domain\Compliance\Kyc\Events\Broadcast\BridgeKycRejected;
use App\Domain\Compliance\Kyc\Events\Broadcast\BridgeVirtualAccountReady;
use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Infrastructure\Bridge\BridgeClient;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the post-KYC-event side effects: broadcast event + push notification +
 * (for approval) auto-provision the user's Bridge virtual account.
 *
 * Extracted from BridgeWebhookController so the controller stays a thin
 * dispatcher and the side-effect chain is testable in isolation.
 *
 * Push fallback follows the locked grilling decision (PR #1090 reply):
 * push fires on kyc.completed and kyc.rejected, NOT on virtual_account.ready
 * (that's a quiet provisioning step the user doesn't need to be paged for).
 *
 * @see docs/BACKEND_HANDOVER_BRIDGE_RAMP.md §3.1, §4.3
 */
final class BridgePostKycHandler
{
    /** Bridge VA destination defaults to Polygon USDC per ADR-0005 (v1 network scope). */
    private const DEFAULT_VA_NETWORK = 'polygon';

    private const DEFAULT_VA_ASSET = 'usdc';

    /**
     * Deep-link route mobile uses on push notification tap. Hardcoded for v1
     * because the Bridge surface has exactly one screen mobile would route to
     * regardless of event type. If we later add separate KYC vs deposit
     * screens, factor this into a per-event const.
     */
    private const MOBILE_ROUTE = '/flows/bridge-setup';

    public function __construct(
        private readonly BridgeClient $client,
        private readonly PushNotificationService $push,
    ) {
    }

    public function handleApproved(BridgeCustomer $customer): void
    {
        $user = User::find($customer->user_id);
        if ($user === null) {
            Log::warning('BridgePostKycHandler: user not found for approved KYC', [
                'user_id' => $customer->user_id,
            ]);

            return;
        }

        BridgeKycCompleted::dispatch($user->id, $customer->bridge_customer_id);

        $this->safePush(
            $user,
            type: 'bridge.kyc.completed',
            title: 'Identity verification approved',
            body: 'Bank-transfer funding via our third-party ramp provider is now available, under its own terms.',
            data: [
                'bridge_customer_id' => $customer->bridge_customer_id,
                'route'              => self::MOBILE_ROUTE,
            ],
        );

        $this->tryProvisionVirtualAccount($customer);
    }

    /**
     * Idempotent VA provisioning callable from outside the KYC webhook path.
     *
     * Used by BlockchainAddressBridgeObserver when a user registers a Polygon
     * address AFTER KYC was already approved (common when KYC completes
     * before mobile finishes wallet setup). Calling repeatedly is safe — the
     * hasVirtualAccount short-circuit + Bridge Idempotency-Key keep it from
     * creating duplicate VAs.
     */
    public function tryProvisionVirtualAccount(BridgeCustomer $customer): void
    {
        $user = User::find($customer->user_id);
        if ($user === null) {
            return;
        }

        $this->provisionVirtualAccount($customer, $user);
    }

    public function handleRejected(BridgeCustomer $customer, ?string $reason = null): void
    {
        $user = User::find($customer->user_id);
        if ($user === null) {
            Log::warning('BridgePostKycHandler: user not found for rejected KYC', [
                'user_id' => $customer->user_id,
            ]);

            return;
        }

        BridgeKycRejected::dispatch($user->id, $customer->bridge_customer_id, $reason);

        $this->safePush(
            $user,
            type: 'bridge.kyc.rejected',
            title: 'Identity verification not approved',
            body: $reason ?? 'Please contact support for next steps.',
            data: [
                'bridge_customer_id' => $customer->bridge_customer_id,
                'route'              => self::MOBILE_ROUTE,
            ],
        );
    }

    private function provisionVirtualAccount(BridgeCustomer $customer, User $user): void
    {
        if ($customer->hasVirtualAccount()) {
            return;
        }

        // blockchain_addresses links to users via user_uuid (not user_id).
        $destinationAddress = BlockchainAddress::where('user_uuid', $user->uuid)
            ->where('chain', self::DEFAULT_VA_NETWORK)
            ->value('address');

        if ($destinationAddress === null || $destinationAddress === '') {
            // User hasn't registered a Polygon wallet address yet — common
            // when KYC completes before mobile finishes wallet setup. The
            // VA can be provisioned later by a separate trigger; for v1 we
            // log and let the user re-trigger by re-opening the Buy screen.
            Log::info('BridgePostKycHandler: deferring VA provisioning — no Polygon address yet', [
                'user_id' => $user->id,
            ]);

            return;
        }

        try {
            $response = $this->client->createVirtualAccount(
                $customer->bridge_customer_id,
                [
                    'source'      => ['currency' => 'usd'],
                    'destination' => [
                        'currency'     => self::DEFAULT_VA_ASSET,
                        'payment_rail' => self::DEFAULT_VA_NETWORK,
                        'to_address'   => $destinationAddress,
                    ],
                ],
                idempotencyKey: 'bridge_va:' . $user->id,
            );
        } catch (Throwable $e) {
            // Bridge call failed — log + continue. The user is KYC-approved
            // but can't onramp yet; a separate operator retry path can
            // re-trigger provisioning. WS event NOT dispatched so mobile
            // accurately shows virtualAccountReady=false.
            Log::error('BridgePostKycHandler: VA provisioning failed', [
                'user_id'   => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        $vaId = (string) ($response['id'] ?? '');
        if ($vaId === '') {
            Log::error('BridgePostKycHandler: Bridge createVirtualAccount returned no id', [
                'user_id'  => $user->id,
                'response' => $response,
            ]);

            return;
        }

        /** @var array<string, mixed> $details */
        $details = (array) ($response['destination'] ?? []) + (array) ($response['source'] ?? []);
        /** @var list<string> $rails */
        $rails = array_values((array) ($response['supported_rails'] ?? ['ach', 'sepa']));

        $customer->update([
            'virtual_account_id'      => $vaId,
            'virtual_account_details' => $details,
            'supported_rails'         => $rails,
        ]);

        BridgeVirtualAccountReady::dispatch($user->id, $customer->bridge_customer_id, $rails);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function safePush(User $user, string $type, string $title, string $body, array $data = []): void
    {
        try {
            $this->push->sendToUser($user, $type, $title, $body, $data);
        } catch (Throwable $e) {
            Log::warning('BridgePostKycHandler: push notification failed', [
                'user_id'   => $user->id,
                'type'      => $type,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
