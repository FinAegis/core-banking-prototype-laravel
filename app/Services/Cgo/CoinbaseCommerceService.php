<?php

namespace App\Services\Cgo;

use App\Models\CgoInvestment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoinbaseCommerceService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://api.commerce.coinbase.com';
    protected string $webhookSecret;

    public function __construct()
    {
        $this->apiKey = config('services.coinbase_commerce.api_key') ?? '';
        $this->webhookSecret = config('services.coinbase_commerce.webhook_secret') ?? '';
    }

    /**
     * Create a charge for crypto payment
     */
    public function createCharge(CgoInvestment $investment): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Coinbase Commerce API key not configured');
        }

        $response = Http::withHeaders([
            'X-CC-Api-Key' => $this->apiKey,
            'X-CC-Version' => '2018-03-22',
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '/charges', [
            'name' => 'CGO Investment - ' . ucfirst($investment->tier),
            'description' => 'Investment in FinAegis Continuous Growth Offering',
            'pricing_type' => 'fixed_price',
            'local_price' => [
                'amount' => (string) $investment->amount,
                'currency' => 'USD',
            ],
            'metadata' => [
                'investment_id' => $investment->id,
                'investment_uuid' => $investment->uuid,
                'tier' => $investment->tier,
                'customer_email' => $investment->email,
            ],
            'redirect_url' => route('cgo.payment.success', ['investment' => $investment->uuid]),
            'cancel_url' => route('cgo.payment.cancel', ['investment' => $investment->uuid]),
        ]);

        if (!$response->successful()) {
            Log::error('Coinbase Commerce charge creation failed', [
                'investment_id' => $investment->id,
                'status' => $response->status(),
                'error' => $response->json(),
            ]);
            throw new \Exception('Failed to create Coinbase Commerce charge: ' . $response->body());
        }

        $chargeData = $response->json()['data'];

        // Store charge details
        $investment->update([
            'coinbase_charge_id' => $chargeData['id'],
            'coinbase_charge_code' => $chargeData['code'],
            'crypto_payment_url' => $chargeData['hosted_url'],
        ]);

        return $chargeData;
    }

    /**
     * Get charge details
     */
    public function getCharge(string $chargeId): array
    {
        $response = Http::withHeaders([
            'X-CC-Api-Key' => $this->apiKey,
            'X-CC-Version' => '2018-03-22',
        ])->get($this->apiUrl . '/charges/' . $chargeId);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch charge details');
        }

        return $response->json()['data'];
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $computedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($signature, $computedSignature);
    }

    /**
     * Process webhook event
     */
    public function processWebhookEvent(array $event): void
    {
        $eventType = $event['type'] ?? '';
        $chargeData = $event['data'] ?? [];

        Log::info('Processing Coinbase Commerce webhook', [
            'event_type' => $eventType,
            'charge_id' => $chargeData['id'] ?? null,
        ]);

        switch ($eventType) {
            case 'charge:created':
                $this->handleChargeCreated($chargeData);
                break;

            case 'charge:confirmed':
                $this->handleChargeConfirmed($chargeData);
                break;

            case 'charge:failed':
                $this->handleChargeFailed($chargeData);
                break;

            case 'charge:delayed':
            case 'charge:pending':
                $this->handleChargePending($chargeData);
                break;

            case 'charge:resolved':
                $this->handleChargeResolved($chargeData);
                break;

            default:
                Log::warning('Unhandled Coinbase Commerce event type', ['type' => $eventType]);
        }
    }

    protected function handleChargeCreated(array $chargeData): void
    {
        $investment = $this->findInvestmentByCharge($chargeData);
        if (!$investment) {
            return;
        }

        Log::info('Coinbase charge created', [
            'investment_id' => $investment->id,
            'charge_code' => $chargeData['code'],
        ]);
    }

    protected function handleChargeConfirmed(array $chargeData): void
    {
        $investment = $this->findInvestmentByCharge($chargeData);
        if (!$investment) {
            return;
        }

        // Get payment details
        $payment = $chargeData['payments'][0] ?? null;
        $paidAmount = $payment['value']['local']['amount'] ?? 0;
        $cryptoCurrency = $payment['value']['crypto']['currency'] ?? 'Unknown';
        $cryptoAmount = $payment['value']['crypto']['amount'] ?? 0;
        $txHash = $payment['transaction_id'] ?? null;

        $investment->update([
            'status' => 'confirmed',
            'payment_status' => 'confirmed',
            'payment_completed_at' => now(),
            'crypto_transaction_hash' => $txHash,
            'crypto_amount_paid' => $cryptoAmount,
            'crypto_currency_paid' => $cryptoCurrency,
            'amount_paid' => $paidAmount,
        ]);

        Log::info('Coinbase charge confirmed', [
            'investment_id' => $investment->id,
            'amount_paid' => $paidAmount,
            'crypto' => $cryptoCurrency,
            'tx_hash' => $txHash,
        ]);

        // Send confirmation email
        try {
            \Mail::to($investment->email)->send(new \App\Mail\CgoInvestmentReceived($investment));
        } catch (\Exception $e) {
            Log::error('Failed to send investment confirmation email', [
                'investment_id' => $investment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function handleChargeFailed(array $chargeData): void
    {
        $investment = $this->findInvestmentByCharge($chargeData);
        if (!$investment) {
            return;
        }

        $investment->update([
            'payment_status' => 'failed',
            'payment_failed_at' => now(),
            'payment_failure_reason' => 'Payment expired or cancelled',
        ]);

        Log::warning('Coinbase charge failed', [
            'investment_id' => $investment->id,
            'charge_code' => $chargeData['code'],
        ]);
    }

    protected function handleChargePending(array $chargeData): void
    {
        $investment = $this->findInvestmentByCharge($chargeData);
        if (!$investment) {
            return;
        }

        $investment->update([
            'payment_status' => 'pending',
            'payment_pending_at' => now(),
        ]);
        
        // Extract crypto payment info if available
        $payment = $chargeData['payments'][0] ?? null;
        if ($payment) {
            $cryptoCurrency = $payment['value']['crypto']['currency'] ?? null;
            $cryptoAmount = $payment['value']['crypto']['amount'] ?? null;
            
            if ($cryptoCurrency && $cryptoAmount) {
                $investment->update([
                    'crypto_currency_paid' => $cryptoCurrency,
                    'crypto_amount_paid' => $cryptoAmount,
                ]);
            }
        }

        Log::info('Coinbase charge pending', [
            'investment_id' => $investment->id,
            'charge_code' => $chargeData['code'] ?? 'unknown',
        ]);
    }

    protected function handleChargeResolved(array $chargeData): void
    {
        $investment = $this->findInvestmentByCharge($chargeData);
        if (!$investment) {
            return;
        }

        // Charge resolved means overpayment was resolved
        $investment->update([
            'status' => 'confirmed',
            'notes' => 'Payment resolved after overpayment',
        ]);

        Log::info('Coinbase charge resolved', [
            'investment_id' => $investment->id,
            'charge_code' => $chargeData['code'],
        ]);
    }

    protected function findInvestmentByCharge(array $chargeData): ?CgoInvestment
    {
        $metadata = $chargeData['metadata'] ?? [];
        $investmentUuid = $metadata['investment_uuid'] ?? null;
        $chargeId = $chargeData['id'] ?? null;

        if ($investmentUuid) {
            return CgoInvestment::where('uuid', $investmentUuid)->first();
        }

        if ($chargeId) {
            return CgoInvestment::where('coinbase_charge_id', $chargeId)->first();
        }

        Log::warning('Coinbase webhook: Investment not found', [
            'charge_id' => $chargeId,
        ]);

        return null;
    }
}