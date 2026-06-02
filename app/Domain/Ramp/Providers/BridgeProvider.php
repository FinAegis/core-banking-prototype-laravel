<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Providers;

use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Infrastructure\Bridge\BridgeClient;
use App\Infrastructure\Bridge\BridgeWebhookVerifier;
use RuntimeException;

/**
 * Bridge.xyz ramp provider.
 *
 * Onramp uses bank-rail virtual accounts: the user wires fiat to the
 * IBAN/account number on their `bridge_customers` row, Bridge auto-converts
 * to USDC, and ships it to the wallet address. `createSession` records the
 * intent + returns the deposit instructions; the actual money movement is
 * detected via the `virtual_account.activity` webhook (handled by
 * BridgeWebhookController) which auto-creates / updates the ramp_sessions
 * row.
 *
 * Offramp is deferred to v1.1 — `createSession` with type='off' throws.
 *
 * KYC events (`customer.kyc_link_*`) are NOT handled here; they go via the
 * dedicated /api/v1/webhooks/bridge controller. `normalizeWebhookPayload`
 * returns null for KYC events so the existing RampWebhookController flow
 * (if Bridge is misconfigured to point at /ramp/webhook/bridge) doesn't
 * silently corrupt KYC state.
 *
 * @see docs/BACKEND_HANDOVER_BRIDGE_RAMP.md §3.1
 * @see docs/adr/0005-bridge-xyz-over-stripe-crypto-onramp.md
 */
class BridgeProvider implements RampProviderInterface
{
    public const PROVIDER_NAME = 'bridge';

    public const DEFAULT_NETWORK = 'polygon';

    /** Fiat ↔ stablecoin (USDC) network where ramped funds land. */
    public const SUPPORTED_NETWORKS = ['polygon'];

    /** Bridge transfer fee in basis points (per dev fee schedule reference). */
    private const BRIDGE_FEE_BPS = 10;

    public function __construct(
        private readonly BridgeClient $client,
        private readonly BridgeWebhookVerifier $verifier,
    ) {
    }

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    /**
     * Onramp: returns deposit instructions for the user's virtual account.
     * Offramp: not yet implemented in v1 (bank-rail only per the brief).
     *
     * For onramp we require:
     *   - user_id in params (RampService injects from User)
     *   - bridge_customers row with virtual_account_id provisioned + KYC approved
     */
    public function createSession(array $params): array
    {
        $type = (string) ($params['type'] ?? '');
        if ($type === 'off') {
            throw new RuntimeException('Bridge offramp is deferred to v1.1.');
        }
        if ($type !== 'on') {
            throw new RuntimeException("Bridge provider does not support type '{$type}'.");
        }

        $userId = isset($params['user_id']) ? (int) $params['user_id'] : null;
        if ($userId === null) {
            throw new RuntimeException('Bridge createSession requires user_id (injected by RampService).');
        }

        $customer = BridgeCustomer::where('user_id', $userId)->first();
        if ($customer === null || ! $customer->isKycApproved() || ! $customer->hasVirtualAccount()) {
            throw new RuntimeException(
                'Bridge onramp requires an approved customer with a provisioned virtual account.'
            );
        }

        $details = $customer->virtual_account_details ?? [];
        $network = strtolower((string) ($params['network'] ?? self::DEFAULT_NETWORK));
        if (! in_array($network, self::SUPPORTED_NETWORKS, true)) {
            throw new RuntimeException("Bridge v1 only supports network 'polygon'; got '{$network}'.");
        }

        // The "session_id" here is a deterministic stable id tying our
        // ramp_sessions row to the user+VA. Bridge events arrive without
        // a session_id (they reference the virtual_account_id and a
        // transfer_id), so we look up by virtual_account_id at webhook
        // time. Returning a session_id prefixed `bridge_va_` makes the
        // mismatch impossible to misread in logs.
        $providerSessionId = 'bridge_va_' . $customer->virtual_account_id;

        return [
            'session_id'           => $providerSessionId,
            'checkout_url'         => null,
            'deposit_instructions' => $this->shapeDepositInstructions($details, $customer),
            'metadata'             => [
                'provider'           => self::PROVIDER_NAME,
                'type'               => 'on',
                'network'            => $network,
                'bridge_customer_id' => $customer->bridge_customer_id,
                'virtual_account_id' => $customer->virtual_account_id,
                'supported_rails'    => $customer->supported_rails ?? [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function shapeDepositInstructions(array $details, BridgeCustomer $customer): array
    {
        return [
            'accountNumber'  => $details['account_number'] ?? null,
            'routingNumber'  => $details['routing_number'] ?? null,
            'iban'           => $details['iban'] ?? null,
            'bic'            => $details['bic'] ?? null,
            'accountName'    => $details['account_holder_name'] ?? null,
            'bankName'       => $details['bank_name'] ?? null,
            'memo'           => $details['memo'] ?? $customer->bridge_customer_id,
            'supportedRails' => $customer->supported_rails ?? [],
        ];
    }

    public function getSessionStatus(string $sessionId): array
    {
        if (str_starts_with($sessionId, 'bridge_va_')) {
            // Virtual-account-initiated onramp sessions don't have a Bridge
            // transfer id to poll until the user actually deposits. The
            // canonical status comes from webhooks; this poll path returns
            // pending (RampService::getSessionStatus already short-circuits
            // for terminal sessions, so this is the natural "still waiting"
            // answer).
            return [
                'status'        => 'pending',
                'fiat_amount'   => null,
                'crypto_amount' => null,
                'metadata'      => [
                    'provider' => self::PROVIDER_NAME,
                    'note'     => 'Onramp session — status updates arrive via Bridge webhook.',
                ],
            ];
        }

        $transfer = $this->client->getTransfer($sessionId);
        $cryptoAmount = isset($transfer['destination_amount']) && is_numeric($transfer['destination_amount'])
            ? (float) $transfer['destination_amount']
            : null;

        return [
            'status'        => $this->mapBridgeTransferStatus((string) ($transfer['state'] ?? '')),
            'fiat_amount'   => null,
            'crypto_amount' => $cryptoAmount,
            'metadata'      => [
                'provider'     => self::PROVIDER_NAME,
                'bridge_state' => $transfer['state'] ?? null,
            ],
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return [
            'fiatCurrencies'   => ['USD', 'EUR', 'GBP'],
            'cryptoCurrencies' => ['USDC'],
            'modes'            => ['buy'],  // 'sell' added when offramp lands
            'limits'           => [
                'minAmount'  => (int) config('ramp.limits.min_fiat_amount', 10),
                'maxAmount'  => (int) config('ramp.limits.max_fiat_amount', 10000),
                'dailyLimit' => (int) config('ramp.limits.daily_limit', 50000),
            ],
        ];
    }

    /**
     * Synthesizes a deterministic quote per ADR-0006:
     *   - Bridge fee = 0.10% of fiat amount
     *   - Network fee = at-cost lookup per network (default Polygon)
     *   - FX spread = 0 in v1 (Bridge doesn't decompose; we don't mark up FX)
     * Zelta markup is added by RampService::getQuotes downstream, not here.
     */
    public function getQuotes(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): array
    {
        if (! is_numeric($fiatAmount) || ! preg_match('/^\d+(\.\d+)?$/', $fiatAmount)) {
            throw new RuntimeException("Bridge quote requires a non-negative numeric fiat amount; got '{$fiatAmount}'.");
        }
        /** @var numeric-string $fiatAmount */
        $amount = bcadd($fiatAmount, '0', 4);

        $bridgeFee = bcadd(bcdiv(bcmul($amount, (string) self::BRIDGE_FEE_BPS, 4), '10000', 4), '0', 2);
        $networkFee = $this->lookupNetworkFee(self::DEFAULT_NETWORK);

        // USDC tracks the dollar 1:1; conversion through EUR/GBP would carry
        // a small implicit FX spread we don't unbundle in v1.
        $cryptoAmount = (float) bcsub($amount, $bridgeFee, 4);

        // Stateless quote_id: encodes the issue timestamp + random tail so
        // RampService can validate freshness at createSession time without a
        // DB lookup. Format: qt_<unix_ts>_<8-hex>. RampService matches on
        // the `qt_` prefix and decodes the timestamp; other providers'
        // quote_id formats are passed through unchecked.
        $quoteId = sprintf('qt_%d_%s', time(), bin2hex(random_bytes(4)));

        return [
            [
                'provider_name'   => 'Bridge',
                'quote_id'        => $quoteId,
                'fiat_amount'     => (float) $amount,
                'crypto_amount'   => $cryptoAmount,
                'exchange_rate'   => 1.0,
                'fee'             => (float) $bridgeFee,
                'network_fee'     => (float) $networkFee,
                'fee_currency'    => $fiatCurrency,
                'payment_methods' => ['ach', 'sepa', 'sepa_instant'],
            ],
        ];
    }

    private function lookupNetworkFee(string $network): string
    {
        // Polygon USDC transfers cost a fraction of a cent. Conservative
        // upper bound published as the at-cost network fee in the quote.
        return match ($network) {
            'polygon' => '0.01',
            default   => '0.50',
        };
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

        if (str_starts_with($type, 'customer.kyc_link_')) {
            // KYC events flow through the dedicated /api/v1/webhooks/bridge
            // controller (BridgeWebhookController) which updates
            // bridge_customers. The ramp seam intentionally ignores them.
            return null;
        }

        $object = $payload['data']['object'] ?? $payload['data'] ?? null;
        if (! is_array($object)) {
            return null;
        }

        if ($type === 'virtual_account.activity') {
            // Incoming deposit detected. Session id = bridge_va_<virtual_account_id>
            // so the ramp_sessions lookup finds the row created by createSession,
            // OR the unsolicited-deposit handler can create one retroactively.
            $virtualAccountId = (string) ($object['virtual_account_id'] ?? $object['id'] ?? '');
            if ($virtualAccountId === '') {
                return null;
            }
            $cryptoAmount = isset($object['destination_amount']) && is_numeric($object['destination_amount'])
                ? bcadd((string) $object['destination_amount'], '0', 8)
                : null;

            return [
                'session_id'    => 'bridge_va_' . $virtualAccountId,
                'status'        => 'processing',  // funds detected, conversion underway
                'crypto_amount' => $cryptoAmount,
                'raw'           => $object,
            ];
        }

        if ($type === 'transfer.completed' || $type === 'transfer.failed') {
            $transferId = (string) ($object['id'] ?? '');
            if ($transferId === '') {
                return null;
            }
            $cryptoAmount = isset($object['destination_amount']) && is_numeric($object['destination_amount'])
                ? bcadd((string) $object['destination_amount'], '0', 8)
                : null;

            return [
                'session_id'    => $transferId,
                'status'        => $type === 'transfer.completed' ? 'completed' : 'failed',
                'crypto_amount' => $cryptoAmount,
                'raw'           => $object,
            ];
        }

        return null;
    }

    private function mapBridgeTransferStatus(string $state): string
    {
        return match (strtolower($state)) {
            'completed', 'paid'              => 'completed',
            'failed', 'returned', 'canceled' => 'failed',
            'pending', 'awaiting_funds'      => 'pending',
            'processing', 'in_progress'      => 'processing',
            default                          => 'pending',
        };
    }
}
