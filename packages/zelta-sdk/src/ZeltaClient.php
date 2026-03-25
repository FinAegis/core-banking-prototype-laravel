<?php

declare(strict_types=1);

namespace Zelta;

use GuzzleHttp\Client;
use Zelta\Contracts\PaymentHandlerInterface;
use Zelta\DataObjects\PaymentConfig;
use Zelta\DataObjects\PaymentResult;
use Zelta\Exceptions\PaymentFailedException;
use Zelta\Exceptions\PaymentRequiredException;

/**
 * Zelta Payment SDK client.
 *
 * Wraps Guzzle HTTP client with automatic x402/MPP payment handling.
 * On 402 response, invokes the payment handler and retries once.
 *
 * Usage:
 *   $client = new ZeltaClient(
 *       config: new PaymentConfig(baseUrl: 'https://api.zelta.app', apiKey: 'zk_live_xxx'),
 *       payment: new X402PaymentHandler($signer),
 *   );
 *   $result = $client->get('/v1/premium/data');
 */
class ZeltaClient
{
    private Client $http;

    public function __construct(
        private readonly PaymentConfig $config,
        private readonly ?PaymentHandlerInterface $payment = null,
    ) {
        $headers = ['Accept' => 'application/json'];
        if ($this->config->apiKey !== null) {
            $headers['Authorization'] = "Bearer {$this->config->apiKey}";
        }

        $this->http = new Client([
            'base_uri'    => rtrim($this->config->baseUrl, '/') . '/',
            'timeout'     => $this->config->timeoutSeconds,
            'headers'     => $headers,
            'http_errors' => false,
        ]);
    }

    /**
     * Perform a GET request with automatic payment handling.
     *
     * @param array<string, mixed> $options Guzzle request options
     */
    public function get(string $path, array $options = []): PaymentResult
    {
        return $this->request('GET', $path, $options);
    }

    /**
     * Perform a POST request with automatic payment handling.
     *
     * @param array<string, mixed> $options Guzzle request options
     */
    public function post(string $path, array $options = []): PaymentResult
    {
        return $this->request('POST', $path, $options);
    }

    /**
     * Perform a PUT request with automatic payment handling.
     *
     * @param array<string, mixed> $options Guzzle request options
     */
    public function put(string $path, array $options = []): PaymentResult
    {
        return $this->request('PUT', $path, $options);
    }

    /**
     * Perform a DELETE request with automatic payment handling.
     *
     * @param array<string, mixed> $options Guzzle request options
     */
    public function delete(string $path, array $options = []): PaymentResult
    {
        return $this->request('DELETE', $path, $options);
    }

    /**
     * Perform an HTTP request with automatic 402 payment retry.
     *
     * @param array<string, mixed> $options Guzzle request options
     */
    public function request(string $method, string $path, array $options = []): PaymentResult
    {
        $response = $this->http->request($method, ltrim($path, '/'), $options);

        // Not a 402 — return as-is
        if ($response->getStatusCode() !== 402) {
            return new PaymentResult(
                body: $this->decodeBody($response->getBody()->getContents()),
                statusCode: $response->getStatusCode(),
            );
        }

        // 402 but no payment handler — throw
        if ($this->payment === null || ! $this->payment->canHandle($response)) {
            throw new PaymentRequiredException(
                url: $path,
                requirements: $this->decodeBody($response->getBody()->getContents()),
            );
        }

        if (! $this->config->autoPay) {
            throw new PaymentRequiredException(
                url: $path,
                requirements: $this->decodeBody($response->getBody()->getContents()),
            );
        }

        // Handle payment and retry once
        $paymentHeaders = $this->payment->handlePaymentRequired($response, $path);
        if ($paymentHeaders === []) {
            throw new PaymentRequiredException(url: $path);
        }

        $retryOptions = $options;
        $retryOptions['headers'] = array_merge(
            $retryOptions['headers'] ?? [],
            $paymentHeaders,
        );

        $retryResponse = $this->http->request($method, ltrim($path, '/'), $retryOptions);

        if ($retryResponse->getStatusCode() >= 400) {
            throw new PaymentFailedException(
                url: $path,
                statusCode: $retryResponse->getStatusCode(),
            );
        }

        return new PaymentResult(
            body: $this->decodeBody($retryResponse->getBody()->getContents()),
            statusCode: $retryResponse->getStatusCode(),
            paid: true,
            paymentMeta: $paymentHeaders,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['raw' => $body];
    }
}
