<?php

declare(strict_types=1);

namespace App\Domain\PaymentRails\Services;

use App\Domain\Banking\Services\IntelligentRoutingService;
use App\Domain\PaymentRails\Enums\PaymentRail;
use App\Domain\PaymentRails\Models\PaymentRailTransaction;
use InvalidArgumentException;

/**
 * Thin router that delegates to IntelligentRoutingService to select the
 * optimal payment rail, then dispatches to the appropriate rail service.
 *
 * Supported beneficiary keys:
 *   name, account_number, routing_number, iban (optional), bic (optional)
 */
final class PaymentRailRouter
{
    public function __construct(
        private readonly IntelligentRoutingService $routing,
        private readonly AchService $ach,
        private readonly FedwireService $fedwire,
        private readonly RtpService $rtp,
        private readonly FedNowService $fednow,
    ) {
    }

    /**
     * Route a payment to the optimal rail and dispatch it.
     *
     * @param  array{
     *     name: string,
     *     account_number?: string,
     *     routing_number?: string,
     *     iban?: string,
     *     bic?: string,
     * } $beneficiary
     * @return array{transaction_id: string, rail: string, status: string, routing_decision: array<string, mixed>}
     */
    public function route(
        int $userId,
        string $amount,
        string $currency,
        string $country,
        string $urgency,
        array $beneficiary,
    ): array {
        // Normalize to numeric-string for bcmath operations downstream
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be numeric');
        }
        /** @var numeric-string $amount */
        $normalizedAmount = $amount;
        $decision = $this->routing->selectOptimalRail($normalizedAmount, $currency, $country, $urgency);

        $recommendedRail = $decision['recommended_rail'];

        $result = $this->dispatch($userId, $recommendedRail, $normalizedAmount, $currency, $beneficiary);

        $this->routing->logDecision(
            rail: $recommendedRail,
            score: $decision['score'],
            factors: $decision['decision_factors'],
            transactionId: $result['transaction_id'],
        );

        return [
            'transaction_id'   => $result['transaction_id'],
            'rail'             => $recommendedRail,
            'status'           => $result['status'],
            'routing_decision' => [
                'recommended_rail' => $decision['recommended_rail'],
                'score'            => $decision['score'],
                'alternatives'     => array_map(
                    static fn (array $alt): string => $alt['rail'],
                    $decision['alternatives'],
                ),
            ],
        ];
    }

    /**
     * Return the available payment rails for a given ISO-3166-1 alpha-2 country code.
     *
     * @return string[]
     */
    public function getSupportedRails(string $country): array
    {
        $upper = strtoupper($country);

        if ($upper === 'US') {
            return [
                PaymentRail::ACH->value,
                PaymentRail::FEDWIRE->value,
                PaymentRail::RTP->value,
                PaymentRail::FEDNOW->value,
            ];
        }

        // Euro-zone countries
        $euCountries = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
            'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
            'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
        ];

        if (in_array($upper, $euCountries, true)) {
            return [
                PaymentRail::SEPA->value,
                PaymentRail::SEPA_INSTANT->value,
            ];
        }

        return [PaymentRail::SWIFT->value];
    }

    /**
     * Look up a PaymentRailTransaction by its UUID and return a status array,
     * or null if not found.
     *
     * @return array{
     *     transaction_id: string,
     *     rail: string,
     *     amount: string,
     *     currency: string,
     *     status: string,
     *     direction: string,
     *     external_id: string|null,
     *     completed_at: string|null,
     *     created_at: string,
     * }|null
     */
    public function getTransactionStatus(string $transactionId, int $userId): ?array
    {
        // Owner only: another user's transaction is "not found".
        /** @var PaymentRailTransaction|null $transaction */
        $transaction = PaymentRailTransaction::query()
            ->whereKey($transactionId)
            ->where('user_id', $userId)
            ->first();

        if ($transaction === null) {
            return null;
        }

        return [
            'transaction_id' => $transaction->id,
            'rail'           => $transaction->rail->value,
            'amount'         => (string) $transaction->amount,
            'currency'       => $transaction->currency,
            'status'         => $transaction->status->value,
            'direction'      => $transaction->direction,
            'external_id'    => $transaction->external_id,
            'completed_at'   => $transaction->completed_at?->toIso8601String(),
            'created_at'     => $transaction->created_at->toIso8601String(),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Dispatch to the concrete rail service that matches $rail.
     *
     * @param  numeric-string $amount
     * @param  array{
     *     name: string,
     *     account_number?: string,
     *     routing_number?: string,
     *     iban?: string,
     *     bic?: string,
     * } $beneficiary
     * @return array{transaction_id: string, status: string}
     */
    private function dispatch(
        int $userId,
        string $rail,
        string $amount,
        string $currency,
        array $beneficiary,
    ): array {
        return match ($rail) {
            PaymentRail::ACH->value     => $this->dispatchAch($userId, $amount, $currency, $beneficiary),
            PaymentRail::FEDWIRE->value => $this->dispatchFedwire($userId, $amount, $currency, $beneficiary),
            PaymentRail::RTP->value     => $this->dispatchRtp($userId, $amount, $currency, $beneficiary),
            PaymentRail::FEDNOW->value  => $this->dispatchFednow($userId, $amount, $currency, $beneficiary),
            // SEPA / SEPA_INSTANT / SWIFT are not yet handled by dedicated services;
            // record a stub transaction so routing still returns a transaction ID.
            default => $this->dispatchFallback($userId, $rail, $amount, $currency, $beneficiary),
        };
    }

    /**
     * @param  array{name: string, account_number?: string, routing_number?: string, iban?: string, bic?: string} $beneficiary
     * @return array{transaction_id: string, status: string}
     */
    private function dispatchAch(int $userId, string $amount, string $currency, array $beneficiary): array
    {
        $accountNumber = $beneficiary['account_number'] ?? '';
        $routingNumber = $beneficiary['routing_number'] ?? '';

        if ($accountNumber === '' || $routingNumber === '') {
            throw new InvalidArgumentException('ACH requires account_number and routing_number.');
        }

        /** @var numeric-string $achAmount */
        $achAmount = $amount;
        $batch = $this->ach->originateCredit(
            userId: $userId,
            routingNumber: $routingNumber,
            accountNumber: $accountNumber,
            amount: $achAmount,
            name: $beneficiary['name'],
        );

        return [
            'transaction_id' => $batch->batch_id,
            'status'         => $batch->status->value,
        ];
    }

    /**
     * @param  array{name: string, account_number?: string, routing_number?: string, iban?: string, bic?: string} $beneficiary
     * @return array{transaction_id: string, status: string}
     */
    private function dispatchFedwire(int $userId, string $amount, string $currency, array $beneficiary): array
    {
        $accountNumber = $beneficiary['account_number'] ?? '';
        $routingNumber = $beneficiary['routing_number'] ?? '';

        if ($accountNumber === '' || $routingNumber === '') {
            throw new InvalidArgumentException('Fedwire requires account_number and routing_number.');
        }

        $result = $this->fedwire->sendTransfer(
            userId: $userId,
            amount: $amount,
            currency: $currency,
            beneficiaryName: $beneficiary['name'],
            beneficiaryAccountNumber: $accountNumber,
            beneficiaryRoutingNumber: $routingNumber,
        );

        return [
            'transaction_id' => (string) $result['transaction_id'],
            'status'         => $result['status'],
        ];
    }

    /**
     * @param  array{name: string, account_number?: string, routing_number?: string, iban?: string, bic?: string} $beneficiary
     * @return array{transaction_id: string, status: string}
     */
    private function dispatchRtp(int $userId, string $amount, string $currency, array $beneficiary): array
    {
        $accountNumber = $beneficiary['account_number'] ?? '';
        $routingNumber = $beneficiary['routing_number'] ?? '';

        if ($accountNumber === '' || $routingNumber === '') {
            throw new InvalidArgumentException('RTP requires account_number and routing_number.');
        }

        $result = $this->rtp->sendPayment(
            userId: $userId,
            amount: $amount,
            currency: $currency,
            creditorName: $beneficiary['name'],
            creditorAccountNumber: $accountNumber,
            creditorRoutingNumber: $routingNumber,
        );

        return [
            'transaction_id' => (string) $result['transaction_id'],
            'status'         => $result['status'],
        ];
    }

    /**
     * @param  array{name: string, account_number?: string, routing_number?: string, iban?: string, bic?: string} $beneficiary
     * @return array{transaction_id: string, status: string}
     */
    private function dispatchFednow(int $userId, string $amount, string $currency, array $beneficiary): array
    {
        $iban = $beneficiary['iban'] ?? '';
        $bic = $beneficiary['bic'] ?? '';

        // FedNow uses ISO 20022 — prefer IBAN/BIC; fall back to synthesised values.
        if ($iban === '') {
            $accountNumber = $beneficiary['account_number'] ?? '';
            $routingNumber = $beneficiary['routing_number'] ?? '';

            if ($accountNumber === '' || $routingNumber === '') {
                throw new InvalidArgumentException('FedNow requires either iban/bic or account_number/routing_number.');
            }

            $iban = 'US' . $routingNumber . $accountNumber;
            $bic = $bic !== '' ? $bic : 'FNBKUS33';
        }

        $result = $this->fednow->sendInstantPayment(
            userId: $userId,
            amount: $amount,
            currency: $currency,
            creditorName: $beneficiary['name'],
            creditorIban: $iban,
            creditorBic: $bic,
        );

        return [
            'transaction_id' => (string) $result['transaction_id'],
            'status'         => $result['status'],
        ];
    }

    /**
     * Stub dispatcher for rails without dedicated services (SEPA, SWIFT, …).
     *
     * @param  array{name: string, account_number?: string, routing_number?: string, iban?: string, bic?: string} $beneficiary
     * @return array{transaction_id: string, status: string}
     */
    private function dispatchFallback(
        int $userId,
        string $rail,
        string $amount,
        string $currency,
        array $beneficiary,
    ): array {
        $transaction = PaymentRailTransaction::create([
            'user_id'   => $userId,
            'rail'      => $rail,
            'amount'    => $amount,
            'currency'  => $currency,
            'status'    => 'pending',
            'direction' => 'debit',
            'metadata'  => [
                'beneficiary_name' => $beneficiary['name'],
                'iban'             => $beneficiary['iban'] ?? null,
                'bic'              => $beneficiary['bic'] ?? null,
            ],
        ]);

        return [
            'transaction_id' => $transaction->id,
            'status'         => $transaction->status->value,
        ];
    }
}
