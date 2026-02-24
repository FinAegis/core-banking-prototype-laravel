<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Connectors;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\Services\FallbackService;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class FlutterwaveConnector extends BaseCustodianConnector
{
    private const API_BASE_URL = 'https://api.flutterwave.com/v3';

    private string $secretKey;

    /** @phpstan-ignore property.onlyWritten (used in production for charge encryption) */
    private string $publicKey;

    /** @phpstan-ignore property.onlyWritten (used in production for payload encryption) */
    private string $encryptionKey;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $config['name'] = $config['name'] ?? 'Flutterwave';
        $config['base_url'] = self::API_BASE_URL;

        // Set keys before parent constructor, since getHeaders() is called during initializeClient()
        $this->secretKey = $config['secret_key'] ?? '';
        $this->publicKey = $config['public_key'] ?? '';
        $this->encryptionKey = $config['encryption_key'] ?? '';

        parent::__construct($config);
    }

    /**
     * @return array<string, string>
     */
    protected function getHeaders(): array
    {
        return [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->secretKey,
        ];
    }

    protected function getHealthCheckEndpoint(): string
    {
        return '/banks/NG';
    }

    public function isAvailable(): bool
    {
        return parent::isAvailable();
    }

    /**
     * Make authenticated API request with resilience.
     *
     * @param  array<string, mixed>  $data
     */
    private function apiRequest(string $method, string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
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
            $response = $this->apiRequest('GET', "/balances/{$assetCode}");

            if (! $response->successful()) {
                throw new Exception('Failed to get balance: ' . $response->body());
            }

            $data = $response->json();

            $balance = new Money(0);
            foreach ($data['data'] ?? [] as $balanceData) {
                if ($balanceData['currency'] === $assetCode) {
                    // Flutterwave returns amounts as float; convert to cents
                    $balance = new Money((int) round(((float) $balanceData['available_balance']) * 100));
                    break;
                }
            }

            $fallbackService->cacheBalance($this->getName(), $accountId, $assetCode, $balance);

            return $balance;
        } catch (Exception $e) {
            $fallbackBalance = $fallbackService->getFallbackBalance($this->getName(), $accountId, $assetCode);

            if ($fallbackBalance !== null) {
                Log::warning('Using fallback balance for Flutterwave', [
                    'account' => $accountId,
                    'asset'   => $assetCode,
                    'error'   => $e->getMessage(),
                ]);

                return $fallbackBalance;
            }

            throw $e;
        }
    }

    public function getAccountInfo(string $accountId): AccountInfo
    {
        $response = $this->apiRequest('GET', '/balances');

        if (! $response->successful()) {
            throw new Exception('Failed to get account info: ' . $response->body());
        }

        $data = $response->json();

        $balances = [];
        foreach ($data['data'] ?? [] as $balance) {
            $balances[$balance['currency']] = (int) round(((float) $balance['available_balance']) * 100);
        }

        return new AccountInfo(
            accountId: $accountId,
            name: 'Flutterwave Account',
            status: 'active',
            balances: $balances,
            currency: 'NGN',
            type: 'merchant',
            createdAt: Carbon::now(),
            metadata: [
                'connector' => 'FlutterwaveConnector',
            ]
        );
    }

    public function initiateTransfer(TransferRequest $request): TransactionReceipt
    {
        $fallbackService = app(FallbackService::class);

        return $this->executeWithResilience(
            serviceIdentifier: 'initiateTransfer',
            operation: function () use ($request) {
                $transferData = [
                    'account_bank'   => $request->metadata['bank_code'] ?? '044',
                    'account_number' => $request->toAccount,
                    'amount'         => $request->amount->getAmount() / 100,
                    'currency'       => $request->assetCode,
                    'narration'      => $request->description ?? $request->reference,
                    'reference'      => $request->reference,
                    'debit_currency' => $request->assetCode,
                ];

                $response = $this->apiRequest('POST', '/transfers', $transferData);

                if (! $response->successful()) {
                    throw new Exception('Failed to initiate transfer: ' . $response->body());
                }

                $data = $response->json()['data'] ?? [];

                return new TransactionReceipt(
                    id: (string) ($data['id'] ?? uniqid('flw_')),
                    status: $this->mapTransactionStatus($data['status'] ?? 'NEW'),
                    fromAccount: $request->fromAccount,
                    toAccount: $data['account_number'] ?? $request->toAccount,
                    assetCode: $data['currency'] ?? $request->assetCode,
                    amount: (int) round(((float) ($data['amount'] ?? 0)) * 100),
                    fee: isset($data['fee']) ? (int) round(((float) $data['fee']) * 100) : null,
                    reference: $data['reference'] ?? $request->reference,
                    createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : Carbon::now(),
                    completedAt: isset($data['complete_message']) ? Carbon::now() : null,
                    metadata: [
                        'flutterwave_status' => $data['status'] ?? 'NEW',
                        'flutterwave_id'     => $data['id'] ?? null,
                        'narration'          => $data['narration'] ?? null,
                    ]
                );
            },
            fallback: function () use ($request, $fallbackService) {
                Log::warning('Flutterwave transfer failed, queueing for retry', [
                    'from'   => $request->fromAccount,
                    'to'     => $request->toAccount,
                    'amount' => $request->amount->getAmount(),
                    'asset'  => $request->assetCode,
                ]);

                return $fallbackService->queueTransferForRetry(
                    $this->getName(),
                    $request->fromAccount,
                    $request->toAccount,
                    $request->amount,
                    $request->assetCode,
                    $request->reference ?? '',
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
                $response = $this->apiRequest('GET', "/transfers/{$transactionId}");

                if (! $response->successful()) {
                    throw new Exception('Failed to get transaction status: ' . $response->body());
                }

                $data = $response->json()['data'] ?? [];

                return new TransactionReceipt(
                    id: (string) ($data['id'] ?? $transactionId),
                    status: $this->mapTransactionStatus($data['status'] ?? 'UNKNOWN'),
                    fromAccount: $data['debit_currency'] ?? '',
                    toAccount: $data['account_number'] ?? '',
                    assetCode: $data['currency'] ?? '',
                    amount: (int) round(((float) ($data['amount'] ?? 0)) * 100),
                    fee: isset($data['fee']) ? (int) round(((float) $data['fee']) * 100) : null,
                    reference: $data['reference'] ?? null,
                    createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : Carbon::now(),
                    completedAt: isset($data['complete_message']) ? Carbon::now() : null,
                    metadata: [
                        'flutterwave_status' => $data['status'] ?? 'UNKNOWN',
                        'flutterwave_id'     => $data['id'] ?? $transactionId,
                    ]
                );
            },
            fallback: function () use ($transactionId, $fallbackService) {
                $status = $fallbackService->getFallbackTransferStatus($this->getName(), $transactionId);

                if ($status !== null) {
                    Log::warning('Using fallback transaction status for Flutterwave', [
                        'transaction_id' => $transactionId,
                    ]);

                    return $status;
                }

                throw new Exception('Cannot retrieve transaction status, service unavailable');
            }
        );
    }

    public function cancelTransaction(string $transactionId): bool
    {
        // Flutterwave doesn't support cancellation of transfers once initiated
        return false;
    }

    /**
     * @return array<string>
     */
    public function getSupportedAssets(): array
    {
        return ['NGN', 'GHS', 'KES', 'ZAR', 'XOF', 'XAF', 'TZS', 'UGX', 'USD', 'EUR', 'GBP'];
    }

    public function validateAccount(string $accountId): bool
    {
        try {
            $response = $this->apiRequest('POST', '/accounts/resolve', [
                'account_number' => $accountId,
                'account_bank'   => '044', // Default to Access Bank for validation
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return ($data['status'] ?? '') === 'success';
            }
        } catch (Exception $e) {
            Log::warning('Account validation failed', [
                'account_id' => $accountId,
                'error'      => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTransactionHistory(string $accountId, ?int $limit = 100, ?int $offset = 0): array
    {
        $response = $this->apiRequest('GET', '/transfers', [
            'page'      => (int) (($offset ?? 0) / max($limit ?? 100, 1)) + 1,
            'page_size' => $limit ?? 100,
        ]);

        if (! $response->successful()) {
            throw new Exception('Failed to get transaction history: ' . $response->body());
        }

        $data = $response->json();
        $transactions = [];

        foreach ($data['data'] ?? [] as $transfer) {
            $transactions[] = [
                'id'           => (string) $transfer['id'],
                'status'       => $this->mapTransactionStatus($transfer['status'] ?? 'UNKNOWN'),
                'from_account' => $transfer['debit_currency'] ?? '',
                'to_account'   => $transfer['account_number'] ?? '',
                'asset_code'   => $transfer['currency'] ?? '',
                'amount'       => (int) round(((float) ($transfer['amount'] ?? 0)) * 100),
                'fee'          => isset($transfer['fee']) ? (int) round(((float) $transfer['fee']) * 100) : null,
                'reference'    => $transfer['reference'] ?? null,
                'created_at'   => $transfer['created_at'] ?? null,
                'completed_at' => $transfer['completed_at'] ?? null,
            ];
        }

        return $transactions;
    }

    /**
     * Map Flutterwave transaction status to internal status.
     */
    private function mapTransactionStatus(string $flutterwaveStatus): string
    {
        return match (strtoupper($flutterwaveStatus)) {
            'NEW', 'PENDING' => 'pending',
            'SUCCESSFUL', 'SUCCESS' => 'completed',
            'FAILED'    => 'failed',
            'CANCELLED' => 'cancelled',
            default     => 'unknown',
        };
    }
}
