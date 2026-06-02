<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services\HyperSwitch;

use App\Domain\Payment\Aggregates\PaymentDepositAggregate;
use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * High-level payment orchestration service via HyperSwitch.
 *
 * Wraps the HyperSwitch REST API with domain-friendly methods
 * for deposit, withdrawal, and refund operations. Handles
 * customer mapping and metadata enrichment.
 */
class HyperSwitchPaymentService
{
    public function __construct(
        private readonly HyperSwitchClient $client,
    ) {
    }

    /**
     * Initiate a card deposit via HyperSwitch.
     *
     * Routes through the best available processor based on HyperSwitch routing config.
     *
     * @return array{payment_id: string, client_secret: string, status: string, connector: string|null}
     */
    public function initiateDeposit(
        int $amountCents,
        string $currency,
        string $userUuid,
        string $userEmail,
        string $returnUrl,
        string $depositUuid,
        ?string $description = null,
    ): array {
        $customerId = $this->ensureCustomer($userUuid, $userEmail);

        $response = $this->client->createPayment([
            'amount'      => $amountCents,
            'currency'    => strtoupper($currency),
            'customer_id' => $customerId,
            'return_url'  => $returnUrl,
            'confirm'     => false, // Two-step: create then confirm with payment method
            'description' => $description ?? 'Deposit',
            'metadata'    => [
                'user_uuid'    => $userUuid,
                'deposit_uuid' => $depositUuid, // round-trips so the webhook can correlate the credit
                'source'       => 'finaegis',
                'created_at'   => gmdate('c'),
            ],
            'statement_descriptor_name' => config('brand.name', 'Zelta'),
        ]);

        Log::info('HyperSwitch: Deposit initiated', [
            'payment_id' => $response['payment_id'] ?? null,
            'amount'     => $amountCents,
            'currency'   => $currency,
            'user'       => $userUuid,
        ]);

        return [
            'payment_id'    => (string) ($response['payment_id'] ?? ''),
            'client_secret' => (string) ($response['client_secret'] ?? ''),
            'status'        => (string) ($response['status'] ?? 'unknown'),
            'connector'     => $response['connector'] ?? null,
        ];
    }

    /**
     * Begin a deposit through HyperSwitch end-to-end: create the payment,
     * initiate the deposit aggregate, and record the intent the webhook will
     * later complete + credit.
     *
     * The payment is created FIRST so a HyperSwitch API failure leaves no
     * dangling aggregate or intent behind. The aggregate is initiated and the
     * intent written synchronously before returning — both well before the
     * client can complete payment and trigger the webhook.
     *
     * @return array{client_secret: string, payment_id: string, deposit_uuid: string}
     */
    public function startDeposit(User $user, int $amountCents, string $currency): array
    {
        $account = $user->accounts()->first();
        if ($account === null) {
            throw new RuntimeException('User has no account to deposit into');
        }

        if ($amountCents <= 0) {
            throw new RuntimeException('Deposit amount must be a positive integer minor-unit value');
        }

        $currency = strtoupper($currency);
        $depositUuid = (string) Str::uuid();
        $returnUrl = (string) (config('hyperswitch.defaults.return_url') ?: url('/wallet'));

        $payment = $this->initiateDeposit(
            amountCents: $amountCents,
            currency: $currency,
            userUuid: (string) $user->uuid,
            userEmail: (string) $user->email,
            returnUrl: $returnUrl,
            depositUuid: $depositUuid,
            description: 'Deposit',
        );

        PaymentDepositAggregate::retrieve($depositUuid)
            ->initiateDeposit(new StripeDeposit(
                accountUuid: (string) $account->uuid,
                amount: $amountCents,
                currency: $currency,
                reference: 'DEP-' . strtoupper(Str::random(12)),
                externalReference: $payment['payment_id'],
                paymentMethod: 'hyperswitch',
                paymentMethodType: 'card',
                metadata: ['processor' => 'hyperswitch'],
            ))
            ->persist();

        HyperSwitchDepositIntent::create([
            'hyperswitch_payment_id' => $payment['payment_id'],
            'deposit_uuid'           => $depositUuid,
            'account_uuid'           => (string) $account->uuid,
            'user_uuid'              => (string) $user->uuid,
            'amount_cents'           => $amountCents,
            'currency'               => $currency,
            'status'                 => HyperSwitchDepositIntent::STATUS_PENDING,
        ]);

        return [
            'client_secret' => $payment['client_secret'],
            'payment_id'    => $payment['payment_id'],
            'deposit_uuid'  => $depositUuid,
        ];
    }

    /**
     * Get payment status from HyperSwitch.
     *
     * @return array{status: string, amount: int, currency: string, connector: string|null, error: string|null}
     */
    public function getPaymentStatus(string $paymentId): array
    {
        $response = $this->client->getPayment($paymentId);

        return [
            'status'    => (string) ($response['status'] ?? 'unknown'),
            'amount'    => (int) ($response['amount'] ?? 0),
            'currency'  => (string) ($response['currency'] ?? ''),
            'connector' => $response['connector'] ?? null,
            'error'     => $response['error_message'] ?? null,
        ];
    }

    /**
     * Process a refund via HyperSwitch.
     *
     * @return array{refund_id: string, status: string}
     */
    public function refund(string $paymentId, int $amountCents, string $reason = ''): array
    {
        $response = $this->client->createRefund($paymentId, $amountCents, $reason);

        Log::info('HyperSwitch: Refund initiated', [
            'payment_id' => $paymentId,
            'refund_id'  => $response['refund_id'] ?? null,
            'amount'     => $amountCents,
        ]);

        return [
            'refund_id' => (string) ($response['refund_id'] ?? ''),
            'status'    => (string) ($response['status'] ?? 'unknown'),
        ];
    }

    /**
     * List available payment connectors.
     *
     * @return array<int, array{name: string, enabled: bool}>
     */
    public function listConnectors(): array
    {
        $connectors = $this->client->listConnectors();

        // API may return indexed array of connector objects or empty
        $result = [];
        foreach ($connectors as $c) {
            if (is_array($c)) {
                $result[] = [
                    'name'    => (string) ($c['connector_name'] ?? 'unknown'),
                    'enabled' => (bool) ($c['disabled'] ?? false) === false,
                ];
            }
        }

        return $result;
    }

    /**
     * Check if HyperSwitch is available and configured.
     *
     * @return array{enabled: bool, available: bool, base_url: string}
     */
    public function status(): array
    {
        $enabled = (bool) config('hyperswitch.enabled', false);

        if (! $enabled) {
            return ['enabled' => false, 'available' => false, 'base_url' => ''];
        }

        $health = $this->client->health();

        return [
            'enabled'   => true,
            'available' => (bool) ($health['available'] ?? false),
            'base_url'  => (string) config('hyperswitch.base_url', ''),
        ];
    }

    /**
     * Ensure a HyperSwitch customer exists for this user.
     */
    private function ensureCustomer(string $userUuid, string $userEmail): string
    {
        $customerId = 'hs_' . Str::slug($userUuid);

        try {
            $this->client->getCustomer($customerId);
        } catch (RuntimeException) {
            // Customer doesn't exist — create
            $this->client->createCustomer([
                'customer_id' => $customerId,
                'email'       => $userEmail,
                'metadata'    => ['finaegis_uuid' => $userUuid],
            ]);
        }

        return $customerId;
    }
}
