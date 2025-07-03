<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Connectors;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Domain\Custodian\ValueObjects\TransferRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeutscheBankConnector extends BaseCustodianConnector
{
    private const API_BASE_URL = 'https://api.db.com/v2';
    private const OAUTH_URL = 'https://api.db.com/oauth2/token';
    
    private string $clientId;
    private string $clientSecret;
    private string $accountId;
    private ?string $accessToken = null;
    private ?Carbon $tokenExpiry = null;

    public function __construct(array $config)
    {
        // Ensure the name is set
        $config['name'] = $config['name'] ?? 'Deutsche Bank';
        // Set base URL for parent class
        $config['base_url'] = self::API_BASE_URL;
        
        parent::__construct($config);
        
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->accountId = $config['account_id'] ?? '';
        
        // Only validate credentials in production
        if (app()->environment('production') && (empty($this->clientId) || empty($this->clientSecret))) {
            throw new \InvalidArgumentException('Deutsche Bank client_id and client_secret are required');
        }
    }

    protected function getHealthCheckEndpoint(): string
    {
        return '/status';
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

        $this->logRequest('POST', self::OAUTH_URL);

        $response = Http::asForm()->post(self::OAUTH_URL, [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'accounts payments sepa instant_payments',
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
     * Make authenticated API request
     */
    private function apiRequest(string $method, string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
        $token = $this->getAccessToken();
        
        $this->logRequest($method, $endpoint, $data);

        $request = Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'X-API-Version' => '2.0',
                'X-Request-ID' => uniqid('db-'),
            ])
            ->timeout(30);

        return match (strtoupper($method)) {
            'GET' => $request->get(self::API_BASE_URL . $endpoint, $data),
            'POST' => $request->post(self::API_BASE_URL . $endpoint, $data),
            'PUT' => $request->put(self::API_BASE_URL . $endpoint, $data),
            'DELETE' => $request->delete(self::API_BASE_URL . $endpoint),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    public function getBalance(string $accountId, string $assetCode): Money
    {
        $response = $this->apiRequest('GET', "/accounts/{$accountId}/balances");

        if (!$response->successful()) {
            throw new \Exception("Failed to get balance: " . $response->body());
        }

        $data = $response->json();
        
        // Deutsche Bank API returns balance information
        foreach ($data['balances'] ?? [] as $balance) {
            if ($balance['currency'] === $assetCode) {
                // Deutsche Bank returns amounts in decimal format, convert to cents
                $amountInCents = (int) round((float) $balance['amount'] * 100);
                return new Money($amountInCents);
            }
        }

        // No balance found for this currency
        return new Money(0);
    }

    public function getAccountInfo(string $accountId): AccountInfo
    {
        $response = $this->apiRequest('GET', "/accounts/{$accountId}");

        if (!$response->successful()) {
            throw new \Exception("Failed to get account info: " . $response->body());
        }

        $data = $response->json();
        
        // Get balance information
        $balancesResponse = $this->apiRequest('GET', "/accounts/{$accountId}/balances");
        $balancesData = $balancesResponse->json();
        
        $balances = [];
        foreach ($balancesData['balances'] ?? [] as $balance) {
            $amountInCents = (int) round((float) $balance['amount'] * 100);
            $balances[$balance['currency']] = $amountInCents;
        }

        return new AccountInfo(
            accountId: $data['accountId'],
            name: $data['accountName'] ?? 'Deutsche Bank Account',
            status: $this->mapAccountStatus($data['status'] ?? 'ACTIVE'),
            balances: $balances,
            currency: $data['currency'] ?? 'EUR',
            type: $data['accountType'] ?? 'CURRENT',
            createdAt: isset($data['openingDate']) ? Carbon::parse($data['openingDate']) : Carbon::now(),
            metadata: [
                'iban' => $data['iban'] ?? null,
                'bic' => $data['bic'] ?? 'DEUTDEFF',
                'branch' => $data['branch'] ?? null,
                'connector' => 'DeutscheBankConnector',
            ]
        );
    }

    public function initiateTransfer(TransferRequest $request): TransactionReceipt
    {
        $endpoint = $this->determineTransferEndpoint($request);
        
        $paymentData = [
            'debtorAccount' => [
                'iban' => $request->fromAccount,
            ],
            'creditorAccount' => [
                'iban' => $request->toAccount,
            ],
            'instructedAmount' => [
                'currency' => $request->assetCode,
                'amount' => number_format($request->amount->getAmount() / 100, 2, '.', ''),
            ],
            'creditorName' => $request->metadata['beneficiary_name'] ?? 'Beneficiary',
            'remittanceInformationUnstructured' => $request->description ?? $request->reference,
            'requestedExecutionDate' => Carbon::now()->format('Y-m-d'),
            'endToEndIdentification' => $request->reference,
        ];

        $response = $this->apiRequest('POST', $endpoint, $paymentData);

        if (!$response->successful()) {
            throw new \Exception("Failed to initiate transfer: " . $response->body());
        }

        $data = $response->json();

        return new TransactionReceipt(
            id: $data['paymentId'],
            status: $this->mapTransactionStatus($data['transactionStatus']),
            fromAccount: $request->fromAccount,
            toAccount: $request->toAccount,
            assetCode: $request->assetCode,
            amount: $request->amount->getAmount(),
            fee: isset($data['fees']) ? (int) round((float) $data['fees']['amount'] * 100) : null,
            reference: $data['endToEndIdentification'] ?? $request->reference,
            createdAt: Carbon::parse($data['acceptanceDateTime']),
            completedAt: isset($data['completionDateTime']) ? Carbon::parse($data['completionDateTime']) : null,
            metadata: [
                'db_payment_id' => $data['paymentId'],
                'db_status' => $data['transactionStatus'],
                'transfer_type' => $endpoint === '/payments/sepa' ? 'SEPA' : 'INSTANT',
            ]
        );
    }

    public function getTransactionStatus(string $transactionId): TransactionReceipt
    {
        $response = $this->apiRequest('GET', "/payments/{$transactionId}");

        if (!$response->successful()) {
            throw new \Exception("Failed to get transaction status: " . $response->body());
        }

        $data = $response->json();

        return new TransactionReceipt(
            id: $data['paymentId'],
            status: $this->mapTransactionStatus($data['transactionStatus']),
            fromAccount: $data['debtorAccount']['iban'] ?? '',
            toAccount: $data['creditorAccount']['iban'] ?? '',
            assetCode: $data['instructedAmount']['currency'],
            amount: (int) round((float) $data['instructedAmount']['amount'] * 100),
            fee: isset($data['fees']) ? (int) round((float) $data['fees']['amount'] * 100) : null,
            reference: $data['endToEndIdentification'] ?? null,
            createdAt: Carbon::parse($data['acceptanceDateTime']),
            completedAt: isset($data['completionDateTime']) ? Carbon::parse($data['completionDateTime']) : null,
            metadata: [
                'db_payment_id' => $data['paymentId'],
                'db_status' => $data['transactionStatus'],
            ]
        );
    }

    public function cancelTransaction(string $transactionId): bool
    {
        $response = $this->apiRequest('DELETE', "/payments/{$transactionId}");

        return $response->successful();
    }

    public function getSupportedAssets(): array
    {
        // Deutsche Bank supports major fiat currencies
        return ['EUR', 'USD', 'GBP', 'CHF', 'JPY', 'CAD', 'AUD', 'NZD', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF'];
    }

    public function validateAccount(string $accountId): bool
    {
        try {
            $response = $this->apiRequest('GET', "/accounts/{$accountId}");
            
            if ($response->successful()) {
                $data = $response->json();
                return in_array($data['status'] ?? '', ['ACTIVE', 'ENABLED']);
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
        $response = $this->apiRequest('GET', "/accounts/{$accountId}/transactions", [
            'limit' => $limit,
            'offset' => $offset,
            'dateFrom' => Carbon::now()->subDays(90)->format('Y-m-d'),
            'dateTo' => Carbon::now()->format('Y-m-d'),
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to get transaction history: " . $response->body());
        }

        $data = $response->json();
        $transactions = [];

        foreach ($data['transactions']['booked'] ?? [] as $transaction) {
            // Determine if this is a debit or credit
            $isDebit = isset($transaction['debtorAccount']['iban']) && 
                       $transaction['debtorAccount']['iban'] === $accountId;
            
            $amount = (int) round((float) $transaction['transactionAmount']['amount'] * 100);
            
            // Make debits negative only if not already negative
            if ($isDebit && $amount > 0) {
                $amount = -$amount;
            }
            
            $transactions[] = [
                'id' => $transaction['transactionId'],
                'status' => 'completed',
                'from_account' => $transaction['debtorAccount']['iban'] ?? $accountId,
                'to_account' => $transaction['creditorAccount']['iban'] ?? $accountId,
                'asset_code' => $transaction['transactionAmount']['currency'],
                'amount' => $amount,
                'fee' => null,
                'reference' => $transaction['endToEndId'] ?? $transaction['mandateId'] ?? null,
                'created_at' => $transaction['bookingDate'],
                'completed_at' => $transaction['valueDate'] ?? $transaction['bookingDate'],
            ];
        }

        return $transactions;
    }

    /**
     * Determine the appropriate transfer endpoint based on the request
     */
    private function determineTransferEndpoint(TransferRequest $request): string
    {
        // Use instant payments for smaller amounts in EUR
        if ($request->assetCode === 'EUR' && $request->amount->getAmount() <= 1500000) { // €15,000
            return '/payments/instant';
        }

        // Default to SEPA for EUR transfers
        if ($request->assetCode === 'EUR') {
            return '/payments/sepa';
        }

        // International wire for non-EUR
        return '/payments/international';
    }

    /**
     * Map Deutsche Bank account status to internal status
     */
    private function mapAccountStatus(string $dbStatus): string
    {
        return match (strtoupper($dbStatus)) {
            'ACTIVE', 'ENABLED' => 'active',
            'PENDING', 'PROCESSING' => 'pending',
            'SUSPENDED', 'BLOCKED' => 'suspended',
            'CLOSED', 'TERMINATED' => 'closed',
            default => 'unknown',
        };
    }

    /**
     * Map Deutsche Bank transaction status to internal status
     */
    private function mapTransactionStatus(string $dbStatus): string
    {
        return match (strtoupper($dbStatus)) {
            'ACCP', 'ACSC', 'ACSP' => 'pending', // Accepted statuses
            'ACCC', 'ACWC' => 'completed', // Completed statuses
            'RJCT', 'CANC' => 'failed', // Rejected or cancelled
            'PDNG', 'RCVD' => 'pending', // Pending statuses
            default => 'unknown',
        };
    }
}