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

class SantanderConnector extends BaseCustodianConnector
{
    private const API_BASE_URL = 'https://api.santander.com/open-banking/v3.1';
    private const AUTH_URL = 'https://auth.santander.com/oauth/token';
    
    private string $apiKey;
    private string $apiSecret;
    private string $certificate;
    private ?string $accessToken = null;
    private ?Carbon $tokenExpiry = null;

    public function __construct(array $config)
    {
        // Ensure the name is set
        $config['name'] = $config['name'] ?? 'Santander';
        
        parent::__construct($config);
        
        $this->apiKey = $config['api_key'] ?? '';
        $this->apiSecret = $config['api_secret'] ?? '';
        $this->certificate = $config['certificate'] ?? '';
        
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new \InvalidArgumentException('Santander api_key and api_secret are required');
        }
    }

    protected function getHealthCheckEndpoint(): string
    {
        return '/health';
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders($this->getCommonHeaders())
                ->get(self::API_BASE_URL . $this->getHealthCheckEndpoint());
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Santander health check failed', [
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Get common headers for API requests
     */
    private function getCommonHeaders(): array
    {
        return [
            'X-Santander-Client-Id' => $this->apiKey,
            'X-Request-ID' => uniqid('san-'),
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get or refresh OAuth access token
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiry && $this->tokenExpiry->isFuture()) {
            return $this->accessToken;
        }

        $this->logRequest('POST', self::AUTH_URL);

        $response = Http::asForm()
            ->withHeaders($this->getCommonHeaders())
            ->post(self::AUTH_URL, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->apiKey,
                'client_secret' => $this->apiSecret,
                'scope' => 'accounts payments fundsconfirmations',
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

        $headers = array_merge($this->getCommonHeaders(), [
            'Authorization' => "Bearer {$token}",
        ]);

        $request = Http::withHeaders($headers)
            ->acceptJson()
            ->timeout(30);

        // Add certificate if provided
        if ($this->certificate) {
            $request = $request->withOptions([
                'cert' => $this->certificate,
            ]);
        }

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
        $response = $this->apiRequest('GET', "/aisp/accounts/{$accountId}/balances");

        if (!$response->successful()) {
            throw new \Exception("Failed to get balance: " . $response->body());
        }

        $data = $response->json();
        
        // Santander follows Open Banking UK standard
        foreach ($data['Data']['Balance'] ?? [] as $balance) {
            if ($balance['Currency'] === $assetCode && $balance['Type'] === 'InterimAvailable') {
                // Convert from decimal to cents
                $amountInCents = (int) round((float) $balance['Amount']['Amount'] * 100);
                return new Money($amountInCents);
            }
        }

        // No balance found for this currency
        return new Money(0);
    }

    public function getAccountInfo(string $accountId): AccountInfo
    {
        $response = $this->apiRequest('GET', "/aisp/accounts/{$accountId}");

        if (!$response->successful()) {
            throw new \Exception("Failed to get account info: " . $response->body());
        }

        $data = $response->json();
        $accountData = $data['Data']['Account'][0] ?? [];
        
        // Get balance information
        $balancesResponse = $this->apiRequest('GET', "/aisp/accounts/{$accountId}/balances");
        $balancesData = $balancesResponse->json();
        
        $balances = [];
        foreach ($balancesData['Data']['Balance'] ?? [] as $balance) {
            if ($balance['Type'] === 'InterimAvailable') {
                $amountInCents = (int) round((float) $balance['Amount']['Amount'] * 100);
                $balances[$balance['Currency']] = $amountInCents;
            }
        }

        return new AccountInfo(
            accountId: $accountData['AccountId'],
            name: $accountData['Nickname'] ?? 'Santander Account',
            status: $this->mapAccountStatus($accountData['Status'] ?? 'Enabled'),
            balances: $balances,
            currency: $accountData['Currency'] ?? 'EUR',
            type: $accountData['AccountSubType'] ?? 'CurrentAccount',
            createdAt: isset($accountData['OpeningDate']) ? Carbon::parse($accountData['OpeningDate']) : Carbon::now(),
            metadata: [
                'account_number' => $accountData['Identification'] ?? null,
                'scheme_name' => $accountData['SchemeName'] ?? null,
                'secondary_identification' => $accountData['SecondaryIdentification'] ?? null,
                'connector' => 'SantanderConnector',
            ]
        );
    }

    public function initiateTransfer(TransferRequest $request): TransactionReceipt
    {
        $consentResponse = $this->createPaymentConsent($request);
        $consentId = $consentResponse['Data']['ConsentId'];
        
        $paymentData = [
            'Data' => [
                'ConsentId' => $consentId,
                'Initiation' => [
                    'InstructionIdentification' => uniqid('SAN-'),
                    'EndToEndIdentification' => $request->reference,
                    'InstructedAmount' => [
                        'Amount' => number_format($request->amount->getAmount() / 100, 2, '.', ''),
                        'Currency' => $request->assetCode,
                    ],
                    'CreditorAccount' => [
                        'SchemeName' => 'IBAN',
                        'Identification' => $request->toAccount,
                        'Name' => $request->metadata['beneficiary_name'] ?? 'Beneficiary',
                    ],
                    'DebtorAccount' => [
                        'SchemeName' => 'IBAN',
                        'Identification' => $request->fromAccount,
                    ],
                    'RemittanceInformation' => [
                        'Unstructured' => $request->description ?? $request->reference,
                    ],
                ],
            ],
            'Risk' => [
                'PaymentContextCode' => 'TransferToThirdParty',
            ],
        ];

        $response = $this->apiRequest('POST', '/pisp/domestic-payments', $paymentData);

        if (!$response->successful()) {
            throw new \Exception("Failed to initiate transfer: " . $response->body());
        }

        $data = $response->json();
        $paymentData = $data['Data'];

        return new TransactionReceipt(
            id: $paymentData['DomesticPaymentId'],
            status: $this->mapTransactionStatus($paymentData['Status']),
            fromAccount: $request->fromAccount,
            toAccount: $request->toAccount,
            assetCode: $request->assetCode,
            amount: $request->amount->getAmount(),
            fee: $this->extractFee($paymentData),
            reference: $paymentData['Initiation']['EndToEndIdentification'],
            createdAt: Carbon::parse($paymentData['CreationDateTime']),
            completedAt: isset($paymentData['StatusUpdateDateTime']) && $paymentData['Status'] === 'AcceptedSettlementCompleted' 
                ? Carbon::parse($paymentData['StatusUpdateDateTime']) 
                : null,
            metadata: [
                'santander_payment_id' => $paymentData['DomesticPaymentId'],
                'santander_status' => $paymentData['Status'],
                'consent_id' => $consentId,
            ]
        );
    }

    public function getTransactionStatus(string $transactionId): TransactionReceipt
    {
        $response = $this->apiRequest('GET', "/pisp/domestic-payments/{$transactionId}");

        if (!$response->successful()) {
            throw new \Exception("Failed to get transaction status: " . $response->body());
        }

        $data = $response->json();
        $paymentData = $data['Data'];

        return new TransactionReceipt(
            id: $paymentData['DomesticPaymentId'],
            status: $this->mapTransactionStatus($paymentData['Status']),
            fromAccount: $paymentData['Initiation']['DebtorAccount']['Identification'] ?? '',
            toAccount: $paymentData['Initiation']['CreditorAccount']['Identification'] ?? '',
            assetCode: $paymentData['Initiation']['InstructedAmount']['Currency'],
            amount: (int) round((float) $paymentData['Initiation']['InstructedAmount']['Amount'] * 100),
            fee: $this->extractFee($paymentData),
            reference: $paymentData['Initiation']['EndToEndIdentification'],
            createdAt: Carbon::parse($paymentData['CreationDateTime']),
            completedAt: isset($paymentData['StatusUpdateDateTime']) && $paymentData['Status'] === 'AcceptedSettlementCompleted'
                ? Carbon::parse($paymentData['StatusUpdateDateTime'])
                : null,
            metadata: [
                'santander_payment_id' => $paymentData['DomesticPaymentId'],
                'santander_status' => $paymentData['Status'],
            ]
        );
    }

    public function cancelTransaction(string $transactionId): bool
    {
        // Santander doesn't support direct payment cancellation in Open Banking
        // Payments can only be rejected during authorization
        Log::warning('Santander does not support payment cancellation', [
            'transaction_id' => $transactionId,
        ]);
        
        return false;
    }

    public function getSupportedAssets(): array
    {
        // Santander supports major fiat currencies across its global network
        return ['EUR', 'GBP', 'USD', 'BRL', 'MXN', 'CLP', 'ARS', 'PLN', 'CHF'];
    }

    public function validateAccount(string $accountId): bool
    {
        try {
            $response = $this->apiRequest('GET', "/aisp/accounts/{$accountId}");
            
            if ($response->successful()) {
                $data = $response->json();
                $status = $data['Data']['Account'][0]['Status'] ?? '';
                return in_array($status, ['Enabled', 'Active']);
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
        $response = $this->apiRequest('GET', "/aisp/accounts/{$accountId}/transactions", [
            'fromBookingDateTime' => Carbon::now()->subDays(90)->toIso8601String(),
            'toBookingDateTime' => Carbon::now()->toIso8601String(),
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to get transaction history: " . $response->body());
        }

        $data = $response->json();
        $transactions = [];

        foreach ($data['Data']['Transaction'] ?? [] as $transaction) {
            $transactions[] = [
                'id' => $transaction['TransactionId'],
                'status' => $transaction['Status'] === 'Booked' ? 'completed' : 'pending',
                'from_account' => $transaction['DebtorAccount']['Identification'] ?? $accountId,
                'to_account' => $transaction['CreditorAccount']['Identification'] ?? $accountId,
                'asset_code' => $transaction['Amount']['Currency'],
                'amount' => (int) round(abs((float) $transaction['Amount']['Amount']) * 100),
                'fee' => isset($transaction['ChargeAmount']) 
                    ? (int) round((float) $transaction['ChargeAmount']['Amount'] * 100) 
                    : null,
                'reference' => $transaction['TransactionReference'] ?? null,
                'created_at' => $transaction['BookingDateTime'],
                'completed_at' => $transaction['ValueDateTime'] ?? $transaction['BookingDateTime'],
            ];

            // Apply limit manually since API might not support it
            if (count($transactions) >= $limit) {
                break;
            }
        }

        // Apply offset
        return array_slice($transactions, $offset, $limit);
    }

    /**
     * Create payment consent before initiating payment
     */
    private function createPaymentConsent(TransferRequest $request): array
    {
        $consentData = [
            'Data' => [
                'Initiation' => [
                    'InstructionIdentification' => uniqid('SANC-'),
                    'EndToEndIdentification' => $request->reference,
                    'InstructedAmount' => [
                        'Amount' => number_format($request->amount->getAmount() / 100, 2, '.', ''),
                        'Currency' => $request->assetCode,
                    ],
                    'CreditorAccount' => [
                        'SchemeName' => 'IBAN',
                        'Identification' => $request->toAccount,
                        'Name' => $request->metadata['beneficiary_name'] ?? 'Beneficiary',
                    ],
                    'RemittanceInformation' => [
                        'Unstructured' => $request->description ?? $request->reference,
                    ],
                ],
            ],
            'Risk' => [
                'PaymentContextCode' => 'TransferToThirdParty',
            ],
        ];

        $response = $this->apiRequest('POST', '/pisp/domestic-payment-consents', $consentData);

        if (!$response->successful()) {
            throw new \Exception("Failed to create payment consent: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Extract fee from payment data
     */
    private function extractFee(array $paymentData): ?int
    {
        if (isset($paymentData['Charges'])) {
            $totalFee = 0;
            foreach ($paymentData['Charges'] as $charge) {
                $totalFee += (int) round((float) $charge['Amount']['Amount'] * 100);
            }
            return $totalFee > 0 ? $totalFee : null;
        }

        return null;
    }

    /**
     * Map Santander account status to internal status
     */
    private function mapAccountStatus(string $santanderStatus): string
    {
        return match ($santanderStatus) {
            'Enabled', 'Active' => 'active',
            'Disabled', 'Pending' => 'pending',
            'Deleted', 'Suspended' => 'suspended',
            'ProForma', 'Closed' => 'closed',
            default => 'unknown',
        };
    }

    /**
     * Map Santander transaction status to internal status
     */
    private function mapTransactionStatus(string $santanderStatus): string
    {
        return match ($santanderStatus) {
            'AcceptedTechnicalValidation', 'AcceptedCustomerProfile', 'AcceptedSettlementInProcess' => 'pending',
            'AcceptedSettlementCompleted', 'AcceptedWithoutPosting' => 'completed',
            'Rejected' => 'failed',
            'Pending' => 'pending',
            default => 'unknown',
        };
    }
}