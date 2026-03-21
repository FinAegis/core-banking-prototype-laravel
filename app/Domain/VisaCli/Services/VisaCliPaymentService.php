<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Services;

use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentRequest;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentResult;
use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use App\Domain\VisaCli\Events\VisaCliPaymentCompleted;
use App\Domain\VisaCli\Events\VisaCliPaymentFailed;
use App\Domain\VisaCli\Events\VisaCliPaymentInitiated;
use App\Domain\VisaCli\Exceptions\VisaCliPaymentException;
use App\Domain\VisaCli\Models\VisaCliPayment;
use App\Domain\VisaCli\Models\VisaCliSpendingLimit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates Visa CLI payments with spending limits and event sourcing.
 */
class VisaCliPaymentService
{
    public function __construct(
        private readonly VisaCliClientInterface $client,
        private readonly VisaCliSpendingLimitService $spendingLimitService,
    ) {
    }

    /**
     * Execute a payment with spending limit enforcement.
     */
    public function executePayment(VisaCliPaymentRequest $request): VisaCliPaymentResult
    {
        if (! config('visacli.enabled', false)) {
            throw new VisaCliPaymentException('Visa CLI integration is not enabled.');
        }

        // Atomic check-and-reserve: prevents race condition on concurrent payments
        $payment = DB::transaction(function () use ($request): VisaCliPayment {
            /** @var VisaCliSpendingLimit|null $limit */
            $limit = VisaCliSpendingLimit::where('agent_id', $request->agentId)
                ->lockForUpdate()
                ->first();

            if ($limit === null) {
                $limit = $this->spendingLimitService->getOrCreateLimit($request->agentId);
            }

            if (! $limit->canSpend($request->amountCents)) {
                throw new VisaCliPaymentException(
                    "Spending limit exceeded for agent {$request->agentId}. "
                    . "Requested: {$request->amountCents} cents."
                );
            }

            // Reserve the spend immediately under lock
            $limit->recordSpending($request->amountCents);

            return VisaCliPayment::create([
                'agent_id'        => $request->agentId,
                'url'             => $request->url,
                'amount_cents'    => $request->amountCents,
                'currency'        => $request->currency,
                'status'          => VisaCliPaymentStatus::PROCESSING,
                'card_identifier' => $request->cardId,
                'metadata'        => array_merge($request->metadata, [
                    'request_id' => $request->requestId,
                    'purpose'    => $request->purpose,
                ]),
            ]);
        });

        event(new VisaCliPaymentInitiated(
            paymentId: $payment->id,
            agentId: $request->agentId,
            url: $request->url,
            amountCents: $request->amountCents,
            currency: $request->currency,
            metadata: ['request_id' => $request->requestId],
        ));

        Log::info('Visa CLI payment initiated', [
            'payment_id' => $payment->id,
            'agent_id'   => $request->agentId,
            'url'        => $request->url,
            'amount'     => $request->amountCents,
        ]);

        try {
            $result = $this->client->pay(
                $request->url,
                $request->amountCents,
                $request->cardId,
            );

            $payment->markCompleted($result->paymentReference);

            event(new VisaCliPaymentCompleted(
                paymentId: $payment->id,
                paymentReference: $result->paymentReference,
                amountCents: $request->amountCents,
                currency: $request->currency,
            ));

            Log::info('Visa CLI payment completed', [
                'payment_id' => $payment->id,
                'reference'  => $result->paymentReference,
            ]);

            return $result;
        } catch (VisaCliPaymentException $e) {
            $payment->markFailed($e->getMessage());

            event(new VisaCliPaymentFailed(
                paymentId: $payment->id,
                reason: $e->getMessage(),
                amountCents: $request->amountCents,
                currency: $request->currency,
            ));

            Log::error('Visa CLI payment failed', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get payment by reference.
     */
    public function getPaymentByReference(string $reference): ?VisaCliPayment
    {
        return VisaCliPayment::where('payment_reference', $reference)->first();
    }

    /**
     * Get recent payments for an agent.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, VisaCliPayment>
     */
    public function getAgentPayments(string $agentId, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return VisaCliPayment::where('agent_id', $agentId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
