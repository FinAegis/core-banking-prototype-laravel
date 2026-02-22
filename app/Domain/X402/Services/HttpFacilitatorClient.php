<?php

declare(strict_types=1);

namespace App\Domain\X402\Services;

use App\Domain\X402\Contracts\FacilitatorClientInterface;
use App\Domain\X402\DataObjects\PaymentPayload;
use App\Domain\X402\DataObjects\PaymentRequirements;
use App\Domain\X402\DataObjects\SettleResponse;
use App\Domain\X402\DataObjects\VerifyResponse;
use App\Domain\X402\Exceptions\X402SettlementException;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpFacilitatorClient implements FacilitatorClientInterface
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $facilitatorUrl,
        private readonly int $timeoutSeconds = 30,
    ) {
    }

    /**
     * Verify a payment authorization via the facilitator.
     */
    public function verify(PaymentPayload $payload, PaymentRequirements $requirements): VerifyResponse
    {
        $url = rtrim($this->facilitatorUrl, '/') . '/verify';

        Log::info('x402: Sending verify request to facilitator', [
            'url'     => $url,
            'network' => $requirements->network,
            'amount'  => $requirements->amount,
        ]);

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->post($url, [
                    'paymentPayload'      => $payload->toArray(),
                    'paymentRequirements' => $requirements->toArray(),
                ]);

            if (! $response->successful()) {
                Log::warning('x402: Facilitator verify returned non-200', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);

                return new VerifyResponse(
                    isValid: false,
                    invalidReason: 'facilitator_error',
                    invalidMessage: 'Facilitator returned HTTP ' . $response->status(),
                );
            }

            /** @var array<string, mixed> $data */
            $data = $response->json();

            return VerifyResponse::fromArray($data);
        } catch (Throwable $e) {
            Log::error('x402: Facilitator verify failed', [
                'error' => $e->getMessage(),
            ]);

            return new VerifyResponse(
                isValid: false,
                invalidReason: 'facilitator_unavailable',
                invalidMessage: 'The payment facilitator is currently unreachable. Please try again shortly.',
            );
        }
    }

    /**
     * Settle a verified payment on-chain via the facilitator.
     */
    public function settle(PaymentPayload $payload, PaymentRequirements $requirements): SettleResponse
    {
        $url = rtrim($this->facilitatorUrl, '/') . '/settle';

        Log::info('x402: Sending settle request to facilitator', [
            'url'     => $url,
            'network' => $requirements->network,
            'amount'  => $requirements->amount,
        ]);

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->post($url, [
                    'paymentPayload'      => $payload->toArray(),
                    'paymentRequirements' => $requirements->toArray(),
                ]);

            if (! $response->successful()) {
                Log::warning('x402: Facilitator settle returned non-200', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);

                return new SettleResponse(
                    success: false,
                    errorReason: 'facilitator_error',
                    errorMessage: 'Facilitator returned HTTP ' . $response->status(),
                );
            }

            /** @var array<string, mixed> $data */
            $data = $response->json();

            return SettleResponse::fromArray($data);
        } catch (Throwable $e) {
            Log::error('x402: Facilitator settle failed', [
                'error' => $e->getMessage(),
            ]);

            throw new X402SettlementException(
                message: 'Facilitator settlement failed: ' . $e->getMessage(),
                errorReason: 'facilitator_unavailable',
                errorMessage: $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Query facilitator for supported schemes, networks, and extensions.
     *
     * @return array<string, mixed>
     */
    public function supported(): array
    {
        $url = rtrim($this->facilitatorUrl, '/') . '/supported';

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->get($url);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [];
        } catch (Throwable $e) {
            Log::warning('x402: Failed to query facilitator capabilities', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
