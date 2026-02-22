<?php

declare(strict_types=1);

namespace App\Domain\X402\Services;

use App\Domain\X402\Contracts\FacilitatorClientInterface;
use App\Domain\X402\DataObjects\MonetizedRouteConfig;
use App\Domain\X402\DataObjects\PaymentPayload;
use App\Domain\X402\DataObjects\PaymentRequirements;
use App\Domain\X402\DataObjects\SettleResponse;
use App\Domain\X402\Enums\SettlementStatus;
use App\Domain\X402\Events\X402PaymentFailed;
use App\Domain\X402\Events\X402PaymentSettled;
use App\Domain\X402\Exceptions\X402SettlementException;
use App\Domain\X402\Models\X402Payment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class X402SettlementService
{
    public function __construct(
        private readonly FacilitatorClientInterface $facilitator,
        private readonly X402PaymentVerificationService $verificationService,
    ) {
    }

    /**
     * Settle a verified payment on-chain and record the result.
     */
    public function settle(PaymentPayload $payload, MonetizedRouteConfig $config): SettleResponse
    {
        $requirements = $this->verificationService->buildRequirements($config);

        // Record payment attempt
        $payment = $this->recordPaymentAttempt($payload, $requirements, $config);

        try {
            Log::info('x402: Initiating settlement', [
                'payment_id' => $payment->id,
                'network'    => $requirements->network,
                'amount'     => $requirements->amount,
            ]);

            $result = $this->facilitator->settle($payload, $requirements);

            $this->updatePaymentFromResult($payment, $result);

            if ($result->success) {
                Event::dispatch(new X402PaymentSettled(
                    paymentId: $payment->id,
                    payerAddress: $result->payer ?? '',
                    transactionHash: $result->transaction,
                    network: $result->network,
                    amount: $requirements->amount,
                ));
            } else {
                Event::dispatch(new X402PaymentFailed(
                    paymentId: $payment->id,
                    errorReason: $result->errorReason ?? 'settlement_failed',
                    errorMessage: $result->errorMessage ?? 'Settlement unsuccessful',
                ));
            }

            return $result;
        } catch (Throwable $e) {
            $safeMessage = $e instanceof X402SettlementException
                ? $e->getMessage()
                : 'An internal error occurred during settlement.';

            $payment->markFailed('settlement_exception', $safeMessage);

            Event::dispatch(new X402PaymentFailed(
                paymentId: $payment->id,
                errorReason: 'settlement_exception',
                errorMessage: $safeMessage,
            ));

            Log::error('x402: Settlement failed with exception', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);

            throw $e instanceof X402SettlementException
                ? $e
                : new X402SettlementException(
                    message: 'Settlement processing failed.',
                    errorReason: 'settlement_exception',
                    errorMessage: 'An internal error occurred during settlement.',
                    previous: $e,
                );
        }
    }

    /**
     * Record a payment attempt in the database.
     */
    private function recordPaymentAttempt(
        PaymentPayload $payload,
        PaymentRequirements $requirements,
        MonetizedRouteConfig $config,
    ): X402Payment {
        $payerAddress = $this->extractPayerAddress($payload);

        return X402Payment::create([
            'payer_address'   => $payerAddress,
            'pay_to_address'  => $requirements->payTo,
            'amount'          => $requirements->amount,
            'network'         => $requirements->network,
            'asset'           => $requirements->asset,
            'scheme'          => $requirements->scheme,
            'status'          => SettlementStatus::PENDING->value,
            'endpoint_method' => $config->method,
            'endpoint_path'   => $config->path,
            'payment_payload' => $payload->toArray(),
        ]);
    }

    /**
     * Update a payment record from a settlement result.
     */
    private function updatePaymentFromResult(X402Payment $payment, SettleResponse $result): void
    {
        if ($result->success) {
            $payment->markSettled($result->transaction);

            if ($result->payer !== null) {
                $payment->payer_address = $result->payer;
                $payment->save();
            }
        } else {
            $payment->markFailed(
                $result->errorReason ?? 'settlement_failed',
                $result->errorMessage ?? 'Settlement was not successful',
            );
        }
    }

    /**
     * Extract the payer address from the payment payload.
     */
    private function extractPayerAddress(PaymentPayload $payload): string
    {
        // EIP-3009 payload
        $authorization = $payload->payload['authorization'] ?? null;
        if (is_array($authorization) && isset($authorization['from'])) {
            return (string) $authorization['from'];
        }

        // Permit2 payload
        $permit2Auth = $payload->payload['permit2Authorization'] ?? null;
        if (is_array($permit2Auth) && isset($permit2Auth['from'])) {
            return (string) $permit2Auth['from'];
        }

        return '0x0000000000000000000000000000000000000000';
    }
}
