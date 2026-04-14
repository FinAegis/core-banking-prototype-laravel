<?php

declare(strict_types=1);

namespace App\Domain\Banking\Connectors;

use App\Domain\Banking\Exceptions\BankAuthenticationException;
use App\Domain\Banking\Exceptions\BankOperationException;
use App\Domain\Banking\Models\BankAccount;
use App\Domain\Banking\Models\BankBalance;
use App\Domain\Banking\Models\BankCapabilities;
use App\Domain\Banking\Models\BankStatement;
use App\Domain\Banking\Models\BankTransaction;
use App\Domain\Banking\Models\BankTransfer;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PSD2/Open Banking API connector for EU bank data.
 *
 * Supports AISP (Account Information Service Provider) and
 * PISP (Payment Initiation Service Provider) operations.
 */
class OpenBankingConnector extends BaseBankConnector
{
    private const CONSENT_CACHE_PREFIX = 'ob_consent:';

    private const TOKEN_CACHE_PREFIX = 'ob_token:';

    private const CONSENT_VALIDITY_DAYS = 90;

    private ?string $consentId = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->bankCode = $config['bank_code'] ?? 'OPENBANKING';
        $this->bankName = $config['bank_name'] ?? 'Open Banking (PSD2)';
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(): void
    {
        $this->ensureNotProduction();

        $clientId = $this->config['client_id'] ?? '';
        $clientSecret = $this->config['client_secret'] ?? '';
        $tokenUrl = $this->config['token_url'] ?? $this->getBaseUrl() . '/oauth2/token';

        $cacheKey = self::TOKEN_CACHE_PREFIX . $this->bankCode;

        /** @var array{access_token: string, expires_in: int}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $this->accessToken = $cached['access_token'];
            $this->tokenExpiry = (new DateTime())->modify('+' . $cached['expires_in'] . ' seconds');

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post($tokenUrl, [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'scope'         => 'accounts payments',
                ]);

            if (! $response->successful()) {
                throw new BankAuthenticationException(
                    "Open Banking authentication failed: HTTP {$response->status()}"
                );
            }

            /** @var array{access_token: string, expires_in: int} $data */
            $data = $response->json();
            $this->accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 3600;
            $this->tokenExpiry = (new DateTime())->modify("+{$expiresIn} seconds");

            Cache::put($cacheKey, $data, $expiresIn - 60);
        } catch (BankAuthenticationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Open Banking authentication error', [
                'bank_code' => $this->bankCode,
                'error'     => $e->getMessage(),
            ]);

            throw new BankAuthenticationException(
                "Open Banking authentication failed: {$e->getMessage()}"
            );
        }
    }

    /**
     * Create or retrieve an AISP consent for account access.
     *
     * @param  array<string>  $accountIds  Account IDs to request consent for
     * @return array{consent_id: string, status: string, redirect_url: string|null}
     */
    public function createConsent(array $accountIds = []): array
    {
        $this->ensureNotProduction();
        $this->ensureAuthenticated();

        $consentUrl = $this->getBaseUrl() . '/consents';

        $payload = [
            'access' => [
                'accounts'     => $accountIds ?: null,
                'balances'     => $accountIds ?: null,
                'transactions' => $accountIds ?: null,
            ],
            'recurringIndicator'       => true,
            'validUntil'               => now()->addDays(self::CONSENT_VALIDITY_DAYS)->format('Y-m-d'),
            'frequencyPerDay'          => 4,
            'combinedServiceIndicator' => false,
        ];

        /** @var array{consentId: string, consentStatus: string, _links: array{scaRedirect: array{href: string}}} $data */
        $data = $this->makeRequest('post', $consentUrl, $payload);

        $this->consentId = $data['consentId'];

        $cacheKey = self::CONSENT_CACHE_PREFIX . $this->bankCode . ':' . $this->consentId;
        Cache::put($cacheKey, [
            'consent_id' => $this->consentId,
            'status'     => $data['consentStatus'],
            'created_at' => now()->toIso8601String(),
        ], now()->addDays(self::CONSENT_VALIDITY_DAYS));

        return [
            'consent_id'   => $this->consentId,
            'status'       => $data['consentStatus'],
            'redirect_url' => $data['_links']['scaRedirect']['href'] ?? null,
        ];
    }

    /**
     * Get consent status.
     */
    public function getConsentStatus(string $consentId): string
    {
        $this->ensureAuthenticated();

        $url = $this->getBaseUrl() . "/consents/{$consentId}/status";

        /** @var array{consentStatus: string} $data */
        $data = $this->makeRequest('get', $url);

        return $data['consentStatus'];
    }

    /**
     * Revoke an existing consent.
     */
    public function revokeConsent(string $consentId): bool
    {
        $this->ensureAuthenticated();

        try {
            $url = $this->getBaseUrl() . "/consents/{$consentId}";

            Http::withToken($this->accessToken ?? '')
                ->timeout(30)
                ->delete($url);

            $cacheKey = self::CONSENT_CACHE_PREFIX . $this->bankCode . ':' . $consentId;
            Cache::forget($cacheKey);

            return true;
        } catch (Exception $e) {
            Log::warning('Failed to revoke Open Banking consent', [
                'consent_id' => $consentId,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all accounts accessible under the current consent (AISP).
     *
     * @return Collection<int, BankAccount>
     */
    public function getAccounts(?string $consentId = null): Collection
    {
        $this->ensureNotProduction();
        $this->ensureAuthenticated();

        $effectiveConsentId = $consentId ?? $this->consentId;
        $url = $this->getBaseUrl() . '/accounts';

        $headers = [];
        if ($effectiveConsentId !== null) {
            $headers['Consent-ID'] = $effectiveConsentId;
        }

        /** @var array{accounts: array<int, array<string, mixed>>} $data */
        $data = $this->makeRequestWithHeaders('get', $url, [], $headers);

        return collect($data['accounts'] ?? [])->map(function (array $acc): BankAccount {
            return new BankAccount(
                id: (string) ($acc['resourceId'] ?? Str::uuid()->toString()),
                bankCode: $this->bankCode,
                accountNumber: (string) ($acc['bban'] ?? ''),
                iban: (string) ($acc['iban'] ?? ''),
                swift: (string) ($acc['bic'] ?? ''),
                currency: (string) ($acc['currency'] ?? 'EUR'),
                accountType: (string) ($acc['cashAccountType'] ?? 'current'),
                status: (string) ($acc['status'] ?? 'active'),
                holderName: (string) ($acc['ownerName'] ?? ''),
                holderAddress: null,
                metadata: [
                    'product'   => $acc['product'] ?? null,
                    'name'      => $acc['name'] ?? null,
                    'psd2_type' => 'AISP',
                ],
                createdAt: Carbon::now(),
                updatedAt: Carbon::now(),
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getAccount(string $accountId): BankAccount
    {
        $this->ensureNotProduction();
        $this->ensureAuthenticated();

        $url = $this->getBaseUrl() . "/accounts/{$accountId}";

        /** @var array{account: array<string, mixed>} $data */
        $data = $this->makeRequest('get', $url);

        $acc = $data['account'] ?? $data;

        return new BankAccount(
            id: (string) ($acc['resourceId'] ?? $accountId),
            bankCode: $this->bankCode,
            accountNumber: (string) ($acc['bban'] ?? ''),
            iban: (string) ($acc['iban'] ?? ''),
            swift: (string) ($acc['bic'] ?? ''),
            currency: (string) ($acc['currency'] ?? 'EUR'),
            accountType: (string) ($acc['cashAccountType'] ?? 'current'),
            status: (string) ($acc['status'] ?? 'active'),
            holderName: (string) ($acc['ownerName'] ?? ''),
            holderAddress: null,
            metadata: [
                'product'   => $acc['product'] ?? null,
                'name'      => $acc['name'] ?? null,
                'psd2_type' => 'AISP',
            ],
            createdAt: Carbon::now(),
            updatedAt: Carbon::now(),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return BankBalance|Collection<int, BankBalance>
     */
    public function getBalance(string $accountId, ?string $currency = null): BankBalance|Collection
    {
        $this->ensureNotProduction();
        $this->ensureAuthenticated();

        $url = $this->getBaseUrl() . "/accounts/{$accountId}/balances";

        /** @var array{account: array<string, mixed>, balances: array<int, array<string, mixed>>} $data */
        $data = $this->makeRequest('get', $url);

        $balances = collect($data['balances'] ?? [])->map(function (array $bal) use ($accountId): BankBalance {
            $amount = $bal['balanceAmount'] ?? [];

            return new BankBalance(
                accountId: $accountId,
                currency: (string) ($amount['currency'] ?? 'EUR'),
                available: (float) ($amount['amount'] ?? 0),
                current: (float) ($amount['amount'] ?? 0),
                pending: 0,
                reserved: 0,
                asOf: Carbon::now(),
                metadata: [
                    'balance_type'   => $bal['balanceType'] ?? null,
                    'reference_date' => $bal['referenceDate'] ?? null,
                ],
            );
        });

        if ($currency !== null) {
            $filtered = $balances->first(fn (BankBalance $b) => $b->currency === $currency);

            return $filtered ?? new BankBalance(
                accountId: $accountId,
                currency: $currency,
                available: 0,
                current: 0,
                pending: 0,
                reserved: 0,
                asOf: Carbon::now(),
            );
        }

        return $balances;
    }

    /**
     * {@inheritDoc}
     *
     * @return Collection<int, BankTransaction>
     */
    public function getTransactions(string $accountId, DateTime $from, DateTime $to, int $limit = 100): Collection
    {
        $this->ensureNotProduction();
        $this->ensureAuthenticated();

        $url = $this->getBaseUrl() . "/accounts/{$accountId}/transactions";

        /** @var array{transactions: array{booked: array<int, array<string, mixed>>, pending: array<int, array<string, mixed>>}} $data */
        $data = $this->makeRequest('get', $url, [
            'dateFrom'      => Carbon::instance($from)->format('Y-m-d'),
            'dateTo'        => Carbon::instance($to)->format('Y-m-d'),
            'bookingStatus' => 'both',
        ]);

        $booked = $data['transactions']['booked'] ?? [];
        $pending = $data['transactions']['pending'] ?? [];

        $allTransactions = array_merge(
            array_map(fn (array $tx) => array_merge($tx, ['_status' => 'booked']), $booked),
            array_map(fn (array $tx) => array_merge($tx, ['_status' => 'pending']), $pending),
        );

        return collect(array_slice($allTransactions, 0, $limit))
            ->map(function (array $tx) use ($accountId): BankTransaction {
                $amount = $tx['transactionAmount'] ?? [];
                $amountValue = (float) ($amount['amount'] ?? 0);

                return new BankTransaction(
                    id: (string) ($tx['transactionId'] ?? Str::uuid()->toString()),
                    bankCode: $this->bankCode,
                    accountId: $accountId,
                    type: $amountValue < 0 ? 'debit' : 'credit',
                    category: (string) ($tx['proprietaryBankTransactionCode'] ?? 'transfer'),
                    amount: $amountValue,
                    currency: (string) ($amount['currency'] ?? 'EUR'),
                    balanceAfter: (float) ($tx['balanceAfterTransaction']['balanceAmount']['amount'] ?? 0),
                    reference: $tx['endToEndId'] ?? null,
                    description: $tx['remittanceInformationUnstructured'] ?? null,
                    counterpartyName: $tx['creditorName'] ?? $tx['debtorName'] ?? null,
                    counterpartyAccount: $tx['creditorAccount']['iban'] ?? $tx['debtorAccount']['iban'] ?? null,
                    counterpartyBank: null,
                    transactionDate: Carbon::parse($tx['bookingDate'] ?? now()),
                    valueDate: Carbon::parse($tx['valueDate'] ?? now()),
                    bookingDate: Carbon::parse($tx['bookingDate'] ?? now()),
                    status: (string) $tx['_status'],
                    metadata: [
                        'entry_reference' => $tx['entryReference'] ?? null,
                        'mandate_id'      => $tx['mandateId'] ?? null,
                    ],
                );
            });
    }

    /**
     * Initiate a SEPA payment (PISP).
     *
     * @param  array<string, mixed>  $transferDetails
     */
    public function initiateTransfer(array $transferDetails): BankTransfer
    {
        $this->ensureNotProduction();
        $this->ensureAuthenticated();

        $url = $this->getBaseUrl() . '/payments/sepa-credit-transfers';

        $payload = [
            'instructedAmount' => [
                'currency' => $transferDetails['currency'] ?? 'EUR',
                'amount'   => number_format((float) ($transferDetails['amount'] ?? 0), 2, '.', ''),
            ],
            'debtorAccount' => [
                'iban' => $transferDetails['from_iban'] ?? '',
            ],
            'creditorName'    => $transferDetails['creditor_name'] ?? '',
            'creditorAccount' => [
                'iban' => $transferDetails['to_iban'] ?? '',
            ],
            'remittanceInformationUnstructured' => $transferDetails['description'] ?? '',
            'endToEndIdentification'            => $transferDetails['reference'] ?? Str::random(16),
        ];

        /** @var array{transactionStatus: string, paymentId: string, _links: array{scaRedirect: array{href: string}}} $data */
        $data = $this->makeRequest('post', $url, $payload);

        return new BankTransfer(
            id: $data['paymentId'] ?? Str::uuid()->toString(),
            bankCode: $this->bankCode,
            type: $transferDetails['type'] ?? 'SEPA',
            status: $this->mapPaymentStatus($data['transactionStatus'] ?? 'RCVD'),
            fromAccountId: $transferDetails['from_account_id'] ?? '',
            toAccountId: $transferDetails['to_account_id'] ?? '',
            toBankCode: $transferDetails['to_bank_code'] ?? $this->bankCode,
            amount: (float) ($transferDetails['amount'] ?? 0),
            currency: $transferDetails['currency'] ?? 'EUR',
            reference: $transferDetails['reference'] ?? null,
            description: $transferDetails['description'] ?? null,
            fees: [],
            exchangeRate: [],
            createdAt: Carbon::now(),
            updatedAt: Carbon::now(),
            executedAt: null,
            failedAt: null,
            failureReason: null,
            metadata: [
                'sca_redirect' => $data['_links']['scaRedirect']['href'] ?? null,
                'psd2_type'    => 'PISP',
            ],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getTransferStatus(string $transferId): BankTransfer
    {
        $this->ensureAuthenticated();

        $url = $this->getBaseUrl() . "/payments/sepa-credit-transfers/{$transferId}/status";

        /** @var array{transactionStatus: string} $data */
        $data = $this->makeRequest('get', $url);

        $status = $this->mapPaymentStatus($data['transactionStatus'] ?? 'RCVD');

        return new BankTransfer(
            id: $transferId,
            bankCode: $this->bankCode,
            type: 'SEPA',
            status: $status,
            fromAccountId: '',
            toAccountId: '',
            toBankCode: $this->bankCode,
            amount: 0,
            currency: 'EUR',
            reference: null,
            description: null,
            fees: [],
            exchangeRate: [],
            createdAt: Carbon::now(),
            updatedAt: Carbon::now(),
            executedAt: $status === 'completed' ? Carbon::now() : null,
            failedAt: $status === 'failed' ? Carbon::now() : null,
            failureReason: $status === 'failed' ? ($data['failureReason'] ?? 'Payment rejected') : null,
            metadata: ['raw_status' => $data['transactionStatus'] ?? null],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function cancelTransfer(string $transferId): bool
    {
        $this->ensureAuthenticated();

        try {
            $url = $this->getBaseUrl() . "/payments/sepa-credit-transfers/{$transferId}";

            Http::withToken($this->accessToken ?? '')
                ->timeout(30)
                ->delete($url);

            return true;
        } catch (Exception $e) {
            Log::warning('Failed to cancel Open Banking transfer', [
                'transfer_id' => $transferId,
                'error'       => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $accountDetails
     */
    public function createAccount(array $accountDetails): BankAccount
    {
        throw new BankOperationException(
            'Account creation is not supported via Open Banking PSD2 APIs'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getStatement(string $accountId, DateTime $from, DateTime $to, string $format = 'JSON'): BankStatement
    {
        $transactions = $this->getTransactions($accountId, $from, $to, 1000);

        $firstTx = $transactions->first();
        $lastTx = $transactions->last();

        $openingBalance = $firstTx instanceof BankTransaction ? ($firstTx->balanceAfter - $firstTx->amount) : 0.0;
        $closingBalance = $lastTx instanceof BankTransaction ? $lastTx->balanceAfter : $openingBalance;

        return new BankStatement(
            id: Str::uuid()->toString(),
            bankCode: $this->bankCode,
            accountId: $accountId,
            periodFrom: Carbon::instance($from),
            periodTo: Carbon::instance($to),
            format: $format,
            openingBalance: $openingBalance,
            closingBalance: $closingBalance,
            currency: 'EUR',
            transactions: $transactions,
            summary: [
                'total_debits'       => $transactions->filter(fn (BankTransaction $tx) => $tx->isDebit())->count(),
                'total_credits'      => $transactions->filter(fn (BankTransaction $tx) => $tx->isCredit())->count(),
                'total_transactions' => $transactions->count(),
            ],
            fileUrl: null,
            fileContent: null,
            generatedAt: Carbon::now(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getCapabilities(): BankCapabilities
    {
        return new BankCapabilities(
            supportedCurrencies: ['EUR', 'GBP', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'CHF'],
            supportedTransferTypes: ['SEPA', 'SEPA_INSTANT'],
            features: ['open_banking', 'psd2', 'aisp', 'pisp', 'consent_management'],
            limits: [
                'SEPA'         => ['EUR' => ['min' => 1, 'max' => 15000000, 'daily' => 50000000]],
                'SEPA_INSTANT' => ['EUR' => ['min' => 1, 'max' => 10000000, 'daily' => 25000000]],
            ],
            fees: [
                'transfer' => ['EUR' => ['fixed' => 0, 'percentage' => 0]],
            ],
            supportsInstantTransfers: true,
            supportsScheduledTransfers: true,
            supportsBulkTransfers: false,
            supportsDirectDebits: true,
            supportsStandingOrders: true,
            supportsVirtualAccounts: false,
            supportsMultiCurrency: true,
            supportsWebhooks: true,
            supportsStatements: true,
            supportsCardIssuance: false,
            maxAccountsPerUser: 50,
            requiredDocuments: [],
            availableCountries: [
                'DE', 'FR', 'ES', 'IT', 'NL', 'BE', 'AT', 'FI', 'IE', 'PT',
                'LT', 'LV', 'EE', 'SK', 'SI', 'LU', 'MT', 'CY', 'GR',
            ],
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public function getSupportedCurrencies(): array
    {
        return ['EUR', 'GBP', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'CHF'];
    }

    /**
     * @return array<string, int>
     */
    public function getTransferLimits(string $accountId, string $transferType): array
    {
        return match ($transferType) {
            'SEPA'         => ['min' => 1, 'max' => 15000000, 'daily' => 50000000],
            'SEPA_INSTANT' => ['min' => 1, 'max' => 10000000, 'daily' => 25000000],
            default        => ['min' => 1, 'max' => 1000000, 'daily' => 5000000],
        };
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function verifyWebhookSignature(string $payload, string $signature, array $headers): bool
    {
        $webhookSecret = $this->config['webhook_secret'] ?? '';
        if ($webhookSecret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * @return array<string, mixed>
     */
    public function processWebhook(string $payload): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true) ?? [];

        return $decoded;
    }

    /**
     * Validate IBAN format and checksum.
     *
     * Overrides BaseBankConnector to add PSD2-specific BIC lookup.
     */
    public function validateIBAN(string $iban): bool
    {
        // Base MOD-97 validation
        if (! parent::validateIBAN($iban)) {
            return false;
        }

        // Additional: check country code is in PSD2 EU/EEA
        $countryCode = strtoupper(substr(str_replace(' ', '', $iban), 0, 2));
        $psd2Countries = [
            'DE', 'FR', 'ES', 'IT', 'NL', 'BE', 'AT', 'FI', 'IE', 'PT',
            'LT', 'LV', 'EE', 'SK', 'SI', 'LU', 'MT', 'CY', 'GR',
            'GB', 'SE', 'NO', 'DK', 'PL', 'CZ', 'CH', 'HU', 'RO', 'BG', 'HR',
        ];

        return in_array($countryCode, $psd2Countries);
    }

    /**
     * {@inheritDoc}
     */
    protected function getHealthCheckUrl(): string
    {
        return $this->getBaseUrl() . '/health';
    }

    /**
     * Get the base URL for the Open Banking API.
     */
    private function getBaseUrl(): string
    {
        return rtrim($this->config['base_url'] ?? 'https://api.openbanking.example.com/v1', '/');
    }

    /**
     * Make an authenticated request with custom headers.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function makeRequestWithHeaders(string $method, string $url, array $data = [], array $headers = []): array
    {
        $this->ensureAuthenticated();

        $request = Http::withToken($this->accessToken ?? '')
            ->timeout(30)
            ->withHeaders($headers);

        $response = $request->$method($url, $data);

        if (! $response->successful()) {
            Log::error('Open Banking API request failed', [
                'bank'     => $this->bankCode,
                'method'   => $method,
                'url'      => $url,
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);

            throw new BankOperationException('Open Banking API request failed: ' . $response->body());
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    /**
     * Map PSD2 payment status codes to internal statuses.
     */
    private function mapPaymentStatus(string $psd2Status): string
    {
        return match (strtoupper($psd2Status)) {
            'RCVD', 'PDNG' => 'pending',
            'ACTC', 'PATC' => 'processing',
            'ACSC', 'ACCC' => 'completed',
            'RJCT', 'CANC' => 'failed',
            'ACWC'         => 'completed',
            default        => 'pending',
        };
    }

    /**
     * Guard: disallow execution in production unless explicitly configured.
     *
     * @throws BankOperationException
     */
    private function ensureNotProduction(): void
    {
        if (app()->environment('production') && ! ($this->config['allow_production'] ?? false)) {
            throw new BankOperationException(
                'Open Banking connector is not enabled for production. Set allow_production=true in config.'
            );
        }
    }
}
