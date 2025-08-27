<?php

declare(strict_types=1);

namespace App\Domain\Banking\Connectors;

use App\Domain\Banking\Contracts\IBankConnector;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseBankConnector implements IBankConnector
{
    protected string $bankCode;

    protected string $bankName;

    protected array $config;

    protected ?string $accessToken = null;

    protected ?DateTime $tokenExpiry = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->bankCode = $config['bank_code'] ?? '';
        $this->bankName = $config['bank_name'] ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getBankCode(): string
    {
        return $this->bankCode;
    }

    /**
     * {@inheritDoc}
     */
    public function getBankName(): string
    {
        return $this->bankName;
    }

    /**
     * {@inheritDoc}
     */
    public function isAvailable(): bool
    {
        $cacheKey = "bank_available:{$this->bankCode}";

        return Cache::remember(
            $cacheKey,
            60,
            function () {
                try {
                    $response = Http::timeout(5)->get($this->getHealthCheckUrl());

                    return $response->successful();
                } catch (Exception $e) {
                    Log::warning(
                        "Bank availability check failed for {$this->bankCode}",
                        [
                            'error' => $e->getMessage(),
                        ]
                    );

                    return false;
                }
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public function validateIBAN(string $iban): bool
    {
        // Remove spaces and convert to uppercase
        $iban = strtoupper(str_replace(' ', '', $iban));

        // Check length
        if (strlen($iban) < 15) {
            return false;
        }

        // Move first 4 characters to end
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        // Replace letters with numbers (A=10, B=11, etc.)
        $numericIban = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numericIban .= ord($char) - ord('A') + 10;
            } else {
                $numericIban .= $char;
            }
        }

        // Calculate mod 97
        $mod = bcmod($numericIban, '97');

        return $mod === '1';
    }

    /**
     * Get health check URL for the bank.
     */
    abstract protected function getHealthCheckUrl(): string;

    /**
     * Make authenticated API request.
     */
    protected function makeRequest(string $method, string $url, array $data = []): array
    {
        $this->ensureAuthenticated();

        $response = Http::withToken($this->accessToken)
            ->timeout(30)
            ->$method($url, $data);

        if (! $response->successful()) {
            Log::error(
                'Bank API request failed',
                [
                    'bank'     => $this->bankCode,
                    'method'   => $method,
                    'url'      => $url,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]
            );

            throw new Exception('Bank API request failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Ensure we have a valid access token.
     */
    protected function ensureAuthenticated(): void
    {
        if ($this->accessToken && $this->tokenExpiry && $this->tokenExpiry > new DateTime()) {
            return;
        }

        $this->authenticate();
    }

    /**
     * Log API request for debugging.
     */
    protected function logRequest(string $method, string $url, array $data = []): void
    {
        if (config('app.debug')) {
            Log::debug(
                'Bank API Request',
                [
                    'bank'   => $this->bankCode,
                    'method' => $method,
                    'url'    => $url,
                    'data'   => $data,
                ]
            );
        }
    }

    /**
     * Log API response for debugging.
     */
    protected function logResponse(string $method, string $url, array $response): void
    {
        if (config('app.debug')) {
            Log::debug(
                'Bank API Response',
                [
                    'bank'     => $this->bankCode,
                    'method'   => $method,
                    'url'      => $url,
                    'response' => $response,
                ]
            );
        }
    }

    /**
     * Convert amount to bank's expected format.
     */
    protected function formatAmount(float $amount, string $currency): int
    {
        // Most banks expect amounts in cents/minor units
        return (int) round($amount * 100);
    }

    /**
     * Parse amount from bank's format.
     */
    protected function parseAmount(int $amount, string $currency): float
    {
        // Convert from cents/minor units to major units
        return $amount / 100;
    }
}
