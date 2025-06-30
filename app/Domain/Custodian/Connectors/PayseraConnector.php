<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Connectors;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use App\Domain\Custodian\Services\FallbackService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayseraConnector extends BaseCustodianConnector
{
    private const API_BASE_URL = 'https://bank.paysera.com/rest/v1';
    private const OAUTH_URL = 'https://bank.paysera.com/oauth/v1';
    
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;
    private ?Carbon $tokenExpiry = null;

    public function __construct(array $config)
    {
        // Ensure the name is set
        $config['name'] = $config['name'] ?? 'Paysera';
        // Set base URL for parent class
        $config['base_url'] = self::API_BASE_URL;
        
        parent::__construct($config);
        
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        
        // Allow empty credentials in testing/development environments
        if ((empty($this->clientId) || empty($this->clientSecret)) && !app()->environment(['local', 'testing'])) {
            throw new \InvalidArgumentException('Paysera client_id and client_secret are required');
        }
    }

    protected function getHealthCheckEndpoint(): string
    {
        return '/health';
    }

    public function isAvailable(): bool
    {
        // Use parent's implementation which includes circuit breaker
        return parent::isAvailable();
    }

    /**
     * Get or refresh OAuth access token
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiry && $this->tokenExpiry->isFuture()) {
            return $this->accessToken;
        }

        $this->logRequest('POST', self::OAUTH_URL . '/token');

        $response = Http::asForm()->post(self::OAUTH_URL . '/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'accounts payments',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to obtain access token: ' . $response->body());
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = Carbon::now()->addSeconds($data['expires_in'] - 60); // Refresh 1 minute early

        return $this->accessToken;
    }

    /**
     * Make authenticated API request with resilience
     */
    private function apiRequest(string $method, string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
        // Update client with fresh token for each request
        $this->client = $this->client->withToken($this->getAccessToken());
        
        return $this->resilientApiRequest(
            method: $method,
            endpoint: self::API_BASE_URL . $endpoint,
            data: $data
        );
    }

    public function getBalance(string $accountId, string $assetCode): Money
    {
        $fallbackService = app(FallbackService::class);
        
        try {
            $response = $this->apiRequest('GET', "/accounts/{$accountId}/balance");

            if (!$response->successful()) {
                throw new \Exception("Failed to get balance: " . $response->body());
            }

            $data = $response->json();
            
            // Paysera returns balances in an array, find the requested currency
            $balance = new Money(0);
            foreach ($data['balances'] ?? [] as $balanceData) {
                if ($balanceData['currency'] === $assetCode) {
                    // Paysera returns amounts in cents
                    $balance = new Money((int) $balanceData['amount']);
                    break;
                }
            }
            
            // Cache the successful balance for future fallback
            $fallbackService->cacheBalance($this->getName(), $accountId, $assetCode, $balance);
            
            return $balance;
            
        } catch (\Exception $e) {
            // Try fallback
            $fallbackBalance = $fallbackService->getFallbackBalance($this->getName(), $accountId, $assetCode);
            
            if ($fallbackBalance !== null) {
                Log::warning("Using fallback balance for Paysera", [
                    'account' => $accountId,
                    'asset' => $assetCode,
                    'error' => $e->getMessage(),
                ]);
                
                return $fallbackBalance;
            }
            
            // No fallback available, rethrow
            throw $e;
        }
    }

    public function getAccountInfo(string $accountId): AccountInfo
    {
        $response = $this->apiRequest('GET', "/accounts/{$accountId}");

        if (!$response->successful()) {
            throw new \Exception("Failed to get account info: " . $response->body());
        }

        $data = $response->json();
        
        // Get all balances
        $balancesResponse = $this->apiRequest('GET', "/accounts/{$accountId}/balance");
        $balancesData = $balancesResponse->json();
        
        $balances = [];
        foreach ($balancesData['balances'] ?? [] as $balance) {
            $balances[$balance['currency']] = (int) $balance['amount'];
        }

        return new AccountInfo(
            accountId: $data['id'],
            name: $data['name'] ?? 'Paysera Account',
            status: $this->mapAccountStatus($data['status'] ?? 'active'),
            balances: $balances,
            currency: $data['default_currency'] ?? 'EUR',
            type: $data['type'] ?? 'personal',
            createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : Carbon::now(),
            metadata: [
                'iban' => $data['iban'] ?? null,
                'bic' => $data['bic'] ?? null,
                'connector' => 'PayseraConnector',
            ]
        );
    }

    public function initiateTransfer(TransferRequest $request): TransactionReceipt
    {
        $fallbackService = app(FallbackService::class);
        
        return $this->executeWithResilience(
            serviceIdentifier: 'initiateTransfer',
            operation: function () use ($request) {
                $paymentData = [
                    'from_account' => $request->fromAccount,
                    'to_account' => $request->toAccount,
                    'amount' => $request->amount->getAmount(),
                    'currency' => $request->assetCode,
                    'description' => $request->description ?? $request->reference,
                    'reference' => $request->reference,
                ];

                $response = $this->apiRequest('POST', '/payments', $paymentData);

                if (!$response->successful()) {
                    throw new \Exception("Failed to initiate transfer: " . $response->body());
                }

                $data = $response->json();

                return new TransactionReceipt(
                    id: $data['id'],
                    status: $this->mapTransactionStatus($data['status']),
                    fromAccount: $data['from_account'],
                    toAccount: $data['to_account'],
                    assetCode: $data['currency'],
                    amount: (int) $data['amount'],
                    fee: isset($data['fee']) ? (int) $data['fee'] : null,
                    reference: $data['reference'] ?? null,
                    createdAt: Carbon::parse($data['created_at']),
                    completedAt: isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null,
                    metadata: [
                        'paysera_status' => $data['status'],
                        'paysera_id' => $data['id'],
                    ]
                );
            },
            fallback: function () use ($request, $fallbackService) {
                // Queue transfer for retry when service is available
                Log::warning("Paysera transfer failed, queueing for retry", [
                    'from' => $request->fromAccount,
                    'to' => $request->toAccount,
                    'amount' => $request->amount->getAmount(),
                    'asset' => $request->assetCode,
                ]);
                
                return $fallbackService->queueTransferForRetry(
                    $this->getName(),
                    $request->fromAccount,
                    $request->toAccount,
                    $request->amount,
                    $request->assetCode,
                    $request->reference,
                    $request->description ?? ''
                );
            }
        );
    }

    public function getTransactionStatus(string $transactionId): TransactionReceipt
    {
        $fallbackService = app(FallbackService::class);
        
        return $this->executeWithResilience(
            serviceIdentifier: 'getTransactionStatus',
            operation: function () use ($transactionId) {
                $response = $this->apiRequest('GET', "/payments/{$transactionId}");

                if (!$response->successful()) {
                    throw new \Exception("Failed to get transaction status: " . $response->body());
                }

                $data = $response->json();

                return new TransactionReceipt(
                    id: $data['id'],
                    status: $this->mapTransactionStatus($data['status']),
                    fromAccount: $data['from_account'],
                    toAccount: $data['to_account'],
                    assetCode: $data['currency'],
                    amount: (int) $data['amount'],
                    fee: isset($data['fee']) ? (int) $data['fee'] : null,
                    reference: $data['reference'] ?? null,
                    createdAt: Carbon::parse($data['created_at']),
                    completedAt: isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null,
                    metadata: [
                        'paysera_status' => $data['status'],
                        'paysera_id' => $data['id'],
                    ]
                );
            },
            fallback: function () use ($transactionId, $fallbackService) {
                // Try to get status from cached/database data
                $status = $fallbackService->getFallbackTransferStatus($this->getName(), $transactionId);
                
                if ($status !== null) {
                    Log::warning("Using fallback transaction status for Paysera", [
                        'transaction_id' => $transactionId,
                    ]);
                    
                    // Return the fallback status directly
                    return $status;
                }
                
                throw new \Exception("Cannot retrieve transaction status, service unavailable");
            }
        );
    }

    public function cancelTransaction(string $transactionId): bool
    {
        $response = $this->apiRequest('POST', "/payments/{$transactionId}/cancel");

        return $response->successful();
    }

    public function getSupportedAssets(): array
    {
        // Paysera primarily supports fiat currencies
        return ['EUR', 'USD', 'GBP', 'CHF', 'PLN', 'DKK', 'NOK', 'SEK', 'CZK', 'HUF', 'RON', 'BGN'];
    }

    public function validateAccount(string $accountId): bool
    {
        try {
            $response = $this->apiRequest('GET', "/accounts/{$accountId}");
            
            if ($response->successful()) {
                $data = $response->json();
                return ($data['status'] ?? '') === 'active';
            }
        } catch (\Exception $e) {
            Log::warning('Account validation failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    public function getTransactionHistory(string $accountId, ?int $limit = 100, ?int $offset = 0): array
    {
        $response = $this->apiRequest('GET', "/accounts/{$accountId}/payments", [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to get transaction history: " . $response->body());
        }

        $data = $response->json();
        $transactions = [];

        foreach ($data['payments'] ?? [] as $payment) {
            $transactions[] = [
                'id' => $payment['id'],
                'status' => $this->mapTransactionStatus($payment['status']),
                'from_account' => $payment['from_account'],
                'to_account' => $payment['to_account'],
                'asset_code' => $payment['currency'],
                'amount' => (int) $payment['amount'],
                'fee' => isset($payment['fee']) ? (int) $payment['fee'] : null,
                'reference' => $payment['reference'] ?? null,
                'created_at' => $payment['created_at'],
                'completed_at' => $payment['completed_at'] ?? null,
            ];
        }

        return $transactions;
    }

    /**
     * Map Paysera account status to internal status
     */
    private function mapAccountStatus(string $payseraStatus): string
    {
        return match ($payseraStatus) {
            'active', 'verified' => 'active',
            'pending', 'unverified' => 'pending',
            'blocked', 'suspended' => 'suspended',
            'closed' => 'closed',
            default => 'unknown',
        };
    }

    /**
     * Map Paysera transaction status to internal status
     */
    private function mapTransactionStatus(string $payseraStatus): string
    {
        return match ($payseraStatus) {
            'created', 'pending', 'processing' => 'pending',
            'completed', 'done' => 'completed',
            'failed', 'rejected' => 'failed',
            'cancelled', 'revoked' => 'cancelled',
            default => 'unknown',
        };
    }
}