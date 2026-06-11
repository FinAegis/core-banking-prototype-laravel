# FinAegis Backend Upgrade Plan for Mobile App v2.4.0

## Status: COMPLETED

> v2.4.0 (Privacy & Identity) has been released and fully implemented. The four domains
> planned in this document -- Privacy, Commerce, TrustCert, and KeyManagement -- are all
> part of the production codebase. For current architecture documentation covering these
> domains, see [CLAUDE.md](../../CLAUDE.md) and [Architecture Overview](../02-ARCHITECTURE/ARCHITECTURE.md).
>
> The content below is preserved for historical reference as the original planning document.

---

**Version**: 2.4.0
**Theme**: Privacy-Preserving Commerce & TrustCert
**Target**: Q3 2026 (COMPLETED)
**Estimated Duration**: 20-24 weeks

---

## Executive Summary

This document outlines the backend enhancements required to support the FinAegis Mobile App with its three core differentiating features:
1. **Stablecoin Commerce** - Shop payments with stablecoins
2. **Privacy Layer** - Untraceable transactions with audit capability
3. **TrustCert** - Blockchain-verified enhanced KYC certificates

---

## 1. Current State Assessment

### 1.1 Existing Capabilities (Leverageable)

| Domain | Capability | Readiness |
|--------|-----------|-----------|
| **Wallet** | Multi-chain support (ETH, Polygon, BSC) | ✅ Ready |
| **Wallet** | HD key derivation (BIP44) | ✅ Ready |
| **Wallet** | Hardware wallet integration | ✅ Ready |
| **Wallet** | Transaction signing | ✅ Ready |
| **Stablecoin** | Issuance/burning | ✅ Ready |
| **Stablecoin** | Collateral management | ✅ Ready |
| **Stablecoin** | Oracle integration | ✅ Ready |
| **Compliance** | KYC verification | ✅ Ready |
| **Compliance** | AML screening | ✅ Ready |
| **Compliance** | Transaction monitoring | ✅ Ready |
| **Mobile** | Device management | ✅ Ready (v2.2.0) |
| **Mobile** | Biometric auth | ✅ Ready (v2.2.0) |
| **Mobile** | Push notifications | ✅ Ready (v2.2.0) |

### 1.2 Gaps to Address

| Gap | Impact | Priority |
|-----|--------|----------|
| No ZK proof infrastructure | Privacy layer impossible | Critical |
| No Shamir key sharding | Non-custodial wallet limited | Critical |
| No merchant payment protocol | Commerce feature blocked | High |
| No SBT/on-chain credentials | TrustCert blocked | High |
| No privacy pool integration | Privacy transactions blocked | High |
| No gas abstraction | Poor UX for payments | Medium |

---

## 2. New Domain Implementations

### 2.1 Privacy Domain

**Purpose**: Handle shielded transactions, ZK proofs, and audit logging

```
app/Domain/Privacy/
├── Models/
│   ├── ShieldedBalance.php         # User's encrypted balance
│   ├── ShieldTransaction.php       # Shield/unshield/transfer records
│   ├── PrivacyProof.php            # Generated ZK proofs
│   ├── AuditVaultEntry.php         # Encrypted audit logs
│   └── NullifierRegistry.php       # Spent nullifiers (prevent double-spend)
│
├── Services/
│   ├── PrivacyPoolService.php      # Core privacy pool operations
│   ├── ZkProverService.php         # ZK proof generation (snarkjs wrapper)
│   ├── ProofOfInnocenceService.php # Sanctions compliance proofs
│   ├── AuditVaultService.php       # Encrypted log management
│   └── NullifierService.php        # Nullifier tracking
│
├── Contracts/
│   ├── PrivacyPoolInterface.php
│   └── ZkProverInterface.php
│
├── Events/
│   ├── FundsShielded.php
│   ├── FundsUnshielded.php
│   ├── PrivateTransferExecuted.php
│   └── AuditLogCreated.php
│
├── Jobs/
│   ├── ProcessShieldTransaction.php
│   ├── GenerateProofOfInnocence.php
│   └── UpdateSanctionsListHash.php
│
├── Workflows/
│   ├── ShieldFundsWorkflow.php
│   └── UnshieldFundsWorkflow.php
│
└── Aggregates/
    └── PrivacyPoolAggregate.php
```

#### Key Service: PrivacyPoolService

```php
<?php

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Contracts\PrivacyPoolInterface;
use App\Domain\Privacy\Models\ShieldTransaction;
use App\Domain\Privacy\Models\AuditVaultEntry;

class PrivacyPoolService implements PrivacyPoolInterface
{
    public function __construct(
        private readonly ZkProverService $zkProver,
        private readonly AuditVaultService $auditVault,
        private readonly BlockchainConnectorInterface $blockchain
    ) {}

    /**
     * Shield funds (deposit to privacy pool)
     */
    public function shield(
        string $userUuid,
        string $tokenAddress,
        string $amount,
        array $proof
    ): ShieldTransaction {
        // 1. Verify ZK proof of ownership
        $this->zkProver->verifyProof($proof, [
            'action' => 'SHIELD',
            'token' => $tokenAddress,
            'amount' => $amount,
        ]);

        // 2. Generate commitment
        $commitment = $this->generateCommitment($userUuid, $tokenAddress, $amount);

        // 3. Submit to on-chain privacy pool
        $txHash = $this->blockchain->callContract(
            config('privacy.pool_address'),
            'deposit',
            [$commitment, $tokenAddress, $amount]
        );

        // 4. Create encrypted audit log
        $auditEntry = $this->auditVault->createEntry([
            'user_uuid' => $userUuid,
            'action' => 'SHIELD',
            'token' => $tokenAddress,
            'amount' => $amount,
            'commitment' => $commitment,
        ]);

        // 5. Record transaction
        return ShieldTransaction::create([
            'user_id' => $userUuid,
            'type' => 'SHIELD',
            'token_address' => $tokenAddress,
            'amount' => $amount,
            'tx_hash' => $txHash,
            'commitment' => $commitment,
            'audit_entry_id' => $auditEntry->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Execute private transfer
     */
    public function transfer(
        string $senderUuid,
        string $recipientViewingKey,
        string $tokenAddress,
        string $amount,
        array $proof
    ): ShieldTransaction {
        // Verify sender has sufficient shielded balance
        // Generate new commitments for sender (change) and recipient
        // Submit ZK proof to chain
        // Create encrypted audit entry
        // Return transaction record
    }

    /**
     * Unshield funds (withdraw from privacy pool)
     */
    public function unshield(
        string $userUuid,
        string $recipientAddress,
        string $tokenAddress,
        string $amount,
        array $proof
    ): ShieldTransaction {
        // Verify proof of ownership in pool
        // Submit nullifier to prevent double-spend
        // Execute withdrawal on-chain
        // Create audit entry
    }
}
```

### 2.2 Commerce Domain

**Purpose**: Handle stablecoin payments, merchant management, settlements

```
app/Domain/Commerce/
├── Models/
│   ├── Merchant.php                # Merchant profiles
│   ├── MerchantApiKey.php          # API authentication
│   ├── PaymentRequest.php          # QR-based payment requests
│   ├── StablecoinPayment.php       # Executed payments
│   ├── Settlement.php              # Batch settlements
│   └── PaymentDispute.php          # Dispute handling
│
├── Services/
│   ├── MerchantOnboardingService.php
│   ├── PaymentRequestService.php   # Generate QR/deep links
│   ├── PaymentExecutionService.php # Process payments
│   ├── SettlementService.php       # Batch settlements
│   ├── FeeCalculationService.php   # Fee management
│   └── RefundService.php
│
├── Events/
│   ├── PaymentRequested.php
│   ├── PaymentReceived.php
│   ├── PaymentConfirmed.php
│   ├── SettlementCompleted.php
│   └── DisputeOpened.php
│
├── Jobs/
│   ├── ProcessPayment.php
│   ├── BatchSettlement.php
│   └── SendPaymentWebhook.php
│
├── Workflows/
│   ├── PaymentWorkflow.php
│   └── SettlementWorkflow.php
│
└── SDK/
    └── MerchantSdkGenerator.php    # TypeScript SDK generation
```

#### Key Service: PaymentExecutionService

```php
<?php

namespace App\Domain\Commerce\Services;

class PaymentExecutionService
{
    public function __construct(
        private readonly BlockchainConnectorInterface $blockchain,
        private readonly PrivacyPoolService $privacyPool,
        private readonly OracleAggregator $oracle,
        private readonly WebhookService $webhook
    ) {}

    /**
     * Execute stablecoin payment
     */
    public function execute(PaymentRequest $request, array $params): StablecoinPayment
    {
        // 1. Validate payment request
        if ($request->isExpired()) {
            throw new PaymentExpiredException();
        }

        // 2. Get current exchange rate
        $rate = $this->oracle->getPrice($params['token'], $request->fiat_currency);
        $tokenAmount = $this->calculateTokenAmount(
            $request->amount,
            $request->fiat_currency,
            $rate
        );

        // 3. Execute based on privacy mode
        if ($params['privacyMode']) {
            $result = $this->executePrivatePayment($request, $tokenAmount, $params);
        } else {
            $result = $this->executePublicPayment($request, $tokenAmount, $params);
        }

        // 4. Create payment record
        $payment = StablecoinPayment::create([
            'merchant_id' => $request->merchant_id,
            'payer_id' => $params['payer_id'],
            'order_id' => $request->order_id,
            'token_address' => $params['token'],
            'amount' => $tokenAmount,
            'fiat_amount' => $request->amount,
            'fiat_currency' => $request->fiat_currency,
            'exchange_rate' => $rate,
            'tx_hash' => $result['txHash'],
            'is_shielded' => $params['privacyMode'],
            'status' => 'pending',
        ]);

        // 5. Send webhook to merchant
        $this->webhook->send($request->merchant->webhook_url, [
            'event' => 'payment.received',
            'payment' => $payment->toArray(),
        ]);

        return $payment;
    }

    private function executePrivatePayment(
        PaymentRequest $request,
        string $amount,
        array $params
    ): array {
        return $this->privacyPool->transfer(
            $params['payer_id'],
            $request->merchant->viewing_key,
            $params['token'],
            $amount,
            $params['proof']
        );
    }

    private function executePublicPayment(
        PaymentRequest $request,
        string $amount,
        array $params
    ): array {
        return $this->blockchain->sendToken(
            $params['from_address'],
            $request->merchant->settlement_address,
            $params['token'],
            $amount,
            $params['signature']
        );
    }
}
```

### 2.3 TrustCert Domain

**Purpose**: Manage enhanced KYC certificates as on-chain credentials

```
app/Domain/TrustCert/
├── Models/
│   ├── Certificate.php             # Issued certificates
│   ├── CertificateApplication.php  # Application records
│   ├── VerificationDocument.php    # Uploaded documents
│   ├── VerificationStep.php        # Verification progress
│   ├── CertificateRevocation.php   # Revocation records
│   └── CertificateType.php         # Type configurations
│
├── Enums/
│   ├── CertType.php               # Certificate types
│   ├── ApplicationStatus.php
│   └── VerificationStatus.php
│
├── Services/
│   ├── ApplicationService.php      # Handle applications
│   ├── VerificationService.php     # Document verification
│   ├── EnhancedKybService.php      # Business verification
│   ├── BackgroundCheckService.php  # Background checks
│   ├── BlockchainMintService.php   # SBT minting
│   ├── ZkCredentialService.php     # ZK proof generation
│   └── ExpirationService.php       # Handle expiring certs
│
├── Events/
│   ├── ApplicationSubmitted.php
│   ├── DocumentVerified.php
│   ├── VerificationCompleted.php
│   ├── CertificateMinted.php
│   ├── CertificateExpiring.php
│   └── CertificateRevoked.php
│
├── Jobs/
│   ├── VerifyDocument.php
│   ├── RunBackgroundCheck.php
│   ├── MintCertificate.php
│   ├── SendExpiryReminder.php
│   └── ProcessRevocation.php
│
├── Workflows/
│   ├── CertificateApplicationWorkflow.php
│   └── CertificateRenewalWorkflow.php
│
├── Contracts/
│   ├── VerificationProviderInterface.php
│   └── BackgroundCheckInterface.php
│
└── SmartContracts/
    ├── TrustCertSBT.sol            # Soulbound Token contract
    └── ProofVerifier.sol           # ZK verification contract
```

#### Key Service: BlockchainMintService

```php
<?php

namespace App\Domain\TrustCert\Services;

use App\Domain\Wallet\Contracts\BlockchainConnectorInterface;
use App\Domain\TrustCert\Models\Certificate;
use App\Domain\TrustCert\Models\CertificateApplication;

class BlockchainMintService
{
    private const CONTRACT_ADDRESS = '0x...'; // TrustCertSBT address

    public function __construct(
        private readonly BlockchainConnectorInterface $blockchain,
        private readonly EncryptionService $encryption
    ) {}

    /**
     * Mint certificate as Soulbound Token
     */
    public function mint(CertificateApplication $application): Certificate
    {
        // 1. Generate credential hash
        $credentialHash = $this->generateCredentialHash($application);

        // 2. Encrypt off-chain data
        $encryptedData = $this->encryption->encryptWithMultiParty(
            $application->getFullCredentialData(),
            config('trustcert.key_holders')
        );

        // 3. Calculate expiry timestamp
        $expiresAt = $this->calculateExpiry($application->cert_type);

        // 4. Mint SBT on-chain
        $txHash = $this->blockchain->callContract(
            self::CONTRACT_ADDRESS,
            'issue',
            [
                $application->user->wallet_address,
                $application->cert_type->value,
                $expiresAt->timestamp,
                $credentialHash,
            ]
        );

        // 5. Wait for confirmation and get token ID
        $receipt = $this->blockchain->waitForReceipt($txHash);
        $tokenId = $this->parseTokenIdFromReceipt($receipt);

        // 6. Create certificate record
        return Certificate::create([
            'user_id' => $application->user_id,
            'application_id' => $application->id,
            'token_id' => $tokenId,
            'wallet_address' => $application->user->wallet_address,
            'cert_type' => $application->cert_type,
            'credential_hash' => $credentialHash,
            'encrypted_data' => $encryptedData,
            'blockchain' => 'polygon',
            'contract_address' => self::CONTRACT_ADDRESS,
            'mint_tx_hash' => $txHash,
            'status' => 'active',
            'issued_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Revoke certificate on-chain
     */
    public function revoke(Certificate $certificate, string $reason): void
    {
        $txHash = $this->blockchain->callContract(
            self::CONTRACT_ADDRESS,
            'revoke',
            [$certificate->token_id, $reason]
        );

        $certificate->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revocation_tx_hash' => $txHash,
            'revocation_reason' => $reason,
        ]);

        event(new CertificateRevoked($certificate));
    }

    /**
     * Verify certificate validity on-chain
     */
    public function verify(string $tokenId): array
    {
        $result = $this->blockchain->callContract(
            self::CONTRACT_ADDRESS,
            'verify',
            [$tokenId],
            'view'
        );

        return [
            'isValid' => $result[0],
            'certType' => CertType::from($result[1]),
            'expiresAt' => Carbon::createFromTimestamp($result[2]),
        ];
    }

    private function generateCredentialHash(CertificateApplication $app): string
    {
        return hash('sha256', json_encode([
            'applicant_id' => $app->user_id,
            'cert_type' => $app->cert_type->value,
            'verification_date' => now()->toIso8601String(),
            'verification_level' => $app->getVerificationLevel(),
            'issuer' => 'finaegis',
        ]));
    }
}
```

### 2.4 KeyManagement Domain Enhancement

**Purpose**: Add Shamir's Secret Sharing for non-custodial key management

```
app/Domain/KeyManagement/
├── Models/
│   ├── KeyShard.php               # Stored key shards
│   ├── ShardHolder.php            # Who holds which shard
│   ├── RecoveryBackup.php         # User recovery backups
│   └── KeyReconstructionLog.php   # Audit of reconstructions
│
├── Services/
│   ├── ShamirService.php          # Core sharding logic
│   ├── KeyReconstructionService.php
│   ├── RecoveryService.php
│   └── ShardDistributionService.php
│
├── HSM/
│   ├── HsmIntegrationService.php  # Cloud HSM connection
│   ├── AwsKmsProvider.php
│   └── AzureKeyVaultProvider.php
│
└── ValueObjects/
    ├── KeyShard.php
    └── ReconstructedKey.php
```

#### Key Service: ShamirService

```php
<?php

namespace App\Domain\KeyManagement\Services;

use App\Domain\KeyManagement\ValueObjects\KeyShard;
use ShamirSecretSharing\Shamir;

class ShamirService
{
    private const TOTAL_SHARDS = 3;
    private const THRESHOLD = 2;  // 2-of-3 required

    public function __construct(
        private readonly HsmIntegrationService $hsm,
        private readonly EncryptionService $encryption
    ) {}

    /**
     * Split a private key into shards
     */
    public function splitKey(string $privateKey, string $userId): array
    {
        // 1. Generate shards using Shamir's Secret Sharing
        $shards = Shamir::share(
            $privateKey,
            self::TOTAL_SHARDS,
            self::THRESHOLD
        );

        // 2. Prepare shard distribution
        return [
            'device' => new KeyShard(
                type: 'device',
                data: $shards[0],
                encryptedFor: 'device-enclave',
                userId: $userId
            ),
            'auth' => new KeyShard(
                type: 'auth',
                data: $this->hsm->encrypt($shards[1]),
                encryptedFor: 'hsm',
                userId: $userId
            ),
            'recovery' => new KeyShard(
                type: 'recovery',
                data: $this->encryption->encryptWithPassword(
                    $shards[2],
                    $userId . ':recovery-password'
                ),
                encryptedFor: 'user-cloud',
                userId: $userId
            ),
        ];
    }

    /**
     * Reconstruct key from shards
     */
    public function reconstructKey(
        KeyShard $shard1,
        KeyShard $shard2
    ): string {
        $decryptedShards = [
            $this->decryptShard($shard1),
            $this->decryptShard($shard2),
        ];

        // Reconstruct using any 2 shards
        return Shamir::recover($decryptedShards);
    }

    /**
     * Get auth shard for signing (HSM-stored)
     */
    public function getAuthShard(string $userId, string $sessionToken): KeyShard
    {
        // Verify session is valid
        $this->verifySession($sessionToken);

        // Retrieve from HSM
        $encryptedShard = $this->hsm->retrieve($userId . ':auth-shard');

        return new KeyShard(
            type: 'auth',
            data: $encryptedShard,
            encryptedFor: 'hsm',
            userId: $userId
        );
    }

    private function decryptShard(KeyShard $shard): string
    {
        return match ($shard->type) {
            'device' => $shard->data, // Already decrypted by device
            'auth' => $this->hsm->decrypt($shard->data),
            'recovery' => $this->encryption->decryptWithPassword(
                $shard->data,
                $shard->userId . ':recovery-password'
            ),
        };
    }
}
```

---

## 3. API Endpoints

### 3.1 Privacy APIs

```php
// routes/api.php

Route::prefix('privacy')->middleware(['auth:sanctum'])->group(function () {
    // Shielded balance
    Route::get('/balance', [PrivacyController::class, 'getBalance']);

    // Shield funds
    Route::post('/shield', [PrivacyController::class, 'shield']);

    // Unshield funds
    Route::post('/unshield', [PrivacyController::class, 'unshield']);

    // Private transfer
    Route::post('/transfer', [PrivacyController::class, 'transfer']);

    // Proof of innocence
    Route::post('/proof-of-innocence', [PrivacyController::class, 'generateProofOfInnocence']);
    Route::get('/proof-of-innocence/{proofId}', [PrivacyController::class, 'getProof']);

    // Viewing key management
    Route::get('/viewing-key', [PrivacyController::class, 'getViewingKey']);
    Route::post('/viewing-key/regenerate', [PrivacyController::class, 'regenerateViewingKey']);
});
```

### 3.2 Commerce APIs

```php
Route::prefix('commerce')->group(function () {
    // Public merchant endpoints
    Route::get('/merchants/{code}', [MerchantController::class, 'show']);

    // Payment requests (public - for QR scanning)
    Route::get('/payment-requests/{id}', [PaymentRequestController::class, 'show']);

    // Authenticated endpoints
    Route::middleware(['auth:sanctum'])->group(function () {
        // Merchant management
        Route::post('/merchants', [MerchantController::class, 'register']);
        Route::put('/merchants/{id}', [MerchantController::class, 'update']);

        // Payment requests
        Route::post('/payment-requests', [PaymentRequestController::class, 'create']);

        // Execute payment
        Route::post('/payments', [PaymentController::class, 'execute']);
        Route::get('/payments/{id}', [PaymentController::class, 'show']);

        // Settlements
        Route::get('/settlements', [SettlementController::class, 'index']);
        Route::get('/settlements/{id}', [SettlementController::class, 'show']);
    });

    // Merchant API (API key auth)
    Route::middleware(['auth:merchant-api'])->group(function () {
        Route::post('/payment-requests', [MerchantApiController::class, 'createPaymentRequest']);
        Route::get('/payments', [MerchantApiController::class, 'listPayments']);
        Route::post('/refunds', [MerchantApiController::class, 'createRefund']);
    });
});
```

### 3.3 TrustCert APIs

```php
Route::prefix('trustcert')->middleware(['auth:sanctum'])->group(function () {
    // Certificate types
    Route::get('/types', [CertificateTypeController::class, 'index']);
    Route::get('/types/{type}/requirements', [CertificateTypeController::class, 'requirements']);

    // Applications
    Route::post('/applications', [ApplicationController::class, 'create']);
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::get('/applications/{id}', [ApplicationController::class, 'show']);
    Route::put('/applications/{id}', [ApplicationController::class, 'update']);
    Route::post('/applications/{id}/submit', [ApplicationController::class, 'submit']);

    // Documents
    Route::post('/applications/{id}/documents', [DocumentController::class, 'upload']);
    Route::delete('/applications/{id}/documents/{docId}', [DocumentController::class, 'delete']);

    // Certificates
    Route::get('/certificates', [CertificateController::class, 'index']);
    Route::get('/certificates/{tokenId}', [CertificateController::class, 'show']);
    Route::delete('/certificates/{tokenId}', [CertificateController::class, 'revoke']);

    // Verification proofs
    Route::post('/certificates/{tokenId}/proof', [CertificateController::class, 'generateProof']);

    // Public verification (no auth)
    Route::get('/verify/{tokenId}', [VerificationController::class, 'verify'])
        ->withoutMiddleware(['auth:sanctum']);
});
```

### 3.4 Key Management APIs

```php
Route::prefix('keys')->middleware(['auth:sanctum'])->group(function () {
    // Shard management
    Route::get('/shards/auth', [KeyShardController::class, 'getAuthShard']);
    Route::post('/shards/recovery/verify', [KeyShardController::class, 'verifyRecoveryShard']);

    // Key reconstruction (for signing)
    Route::post('/reconstruct', [KeyReconstructionController::class, 'reconstruct']);

    // Recovery
    Route::post('/recovery/initiate', [RecoveryController::class, 'initiate']);
    Route::post('/recovery/complete', [RecoveryController::class, 'complete']);
});
```

---

## 4. Database Schema

### 4.1 Migration Files

```bash
# Privacy Domain
2026_02_15_000001_create_shielded_balances_table.php
2026_02_15_000002_create_shield_transactions_table.php
2026_02_15_000003_create_privacy_proofs_table.php
2026_02_15_000004_create_audit_vault_entries_table.php
2026_02_15_000005_create_nullifier_registry_table.php

# Commerce Domain
2026_02_15_000010_create_merchants_table.php
2026_02_15_000011_create_merchant_api_keys_table.php
2026_02_15_000012_create_payment_requests_table.php
2026_02_15_000013_create_stablecoin_payments_table.php
2026_02_15_000014_create_settlements_table.php
2026_02_15_000015_create_payment_disputes_table.php

# TrustCert Domain
2026_02_15_000020_create_certificate_types_table.php
2026_02_15_000021_create_certificates_table.php
2026_02_15_000022_create_certificate_applications_table.php
2026_02_15_000023_create_verification_documents_table.php
2026_02_15_000024_create_verification_steps_table.php
2026_02_15_000025_create_certificate_revocations_table.php

# Key Management Domain
2026_02_15_000030_create_key_shards_table.php
2026_02_15_000031_create_shard_holders_table.php
2026_02_15_000032_create_recovery_backups_table.php
2026_02_15_000033_create_key_reconstruction_logs_table.php
```

### 4.2 Core Tables

```php
// Privacy: shielded_balances
Schema::create('shielded_balances', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('user_id');
    $table->string('token_address', 42);
    $table->string('commitment', 66)->unique();
    $table->decimal('amount', 36, 18);
    $table->string('nullifier_hash', 66)->unique();
    $table->boolean('is_spent')->default(false);
    $table->timestamp('spent_at')->nullable();
    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users');
    $table->index(['user_id', 'token_address', 'is_spent']);
});

// Commerce: merchants
Schema::create('merchants', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('user_id');
    $table->string('business_name');
    $table->string('merchant_code', 20)->unique();
    $table->string('business_type', 50);
    $table->string('country', 2);
    $table->json('accepted_tokens');
    $table->string('settlement_address', 42);
    $table->string('viewing_key', 66)->nullable(); // For privacy payments
    $table->enum('settlement_frequency', ['instant', 'daily', 'weekly']);
    $table->decimal('fee_rate', 5, 4)->default(0.0100); // 1%
    $table->boolean('is_verified')->default(false);
    $table->boolean('is_active')->default(true);
    $table->string('webhook_url')->nullable();
    $table->string('webhook_secret', 64)->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('user_id')->references('id')->on('users');
});

// TrustCert: certificates
Schema::create('certificates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('user_id');
    $table->uuid('application_id');
    $table->string('token_id', 78)->unique(); // uint256 as hex
    $table->string('wallet_address', 42);
    $table->string('cert_type', 30);
    $table->string('credential_hash', 66);
    $table->text('encrypted_data');
    $table->string('blockchain', 20);
    $table->string('contract_address', 42);
    $table->string('mint_tx_hash', 66)->nullable();
    $table->string('status', 20)->default('pending');
    $table->timestamp('issued_at')->nullable();
    $table->timestamp('expires_at');
    $table->timestamp('revoked_at')->nullable();
    $table->string('revocation_tx_hash', 66)->nullable();
    $table->text('revocation_reason')->nullable();
    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users');
    $table->foreign('application_id')->references('id')->on('certificate_applications');
    $table->index(['user_id', 'status']);
    $table->index(['wallet_address', 'cert_type']);
});

// Key Management: key_shards
Schema::create('key_shards', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('user_id');
    $table->enum('shard_type', ['device', 'auth', 'recovery']);
    $table->text('encrypted_shard');
    $table->string('encryption_method', 30);
    $table->string('key_id', 100)->nullable(); // HSM key reference
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_used_at')->nullable();
    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users');
    $table->unique(['user_id', 'shard_type']);
});
```

---

## 5. Configuration

### 5.1 New Config Files

```php
// config/privacy.php
return [
    'pool_address' => env('PRIVACY_POOL_ADDRESS'),
    'pool_network' => env('PRIVACY_POOL_NETWORK', 'polygon'),

    'zk_prover' => [
        'circuit_path' => storage_path('circuits/shield.wasm'),
        'proving_key' => storage_path('circuits/shield.zkey'),
        'verification_key' => storage_path('circuits/shield.vkey'),
    ],

    'audit_vault' => [
        'key_holders' => [
            'finaegis_compliance' => env('AUDIT_KEY_FINAEGIS'),
            'external_auditor' => env('AUDIT_KEY_AUDITOR'),
            'legal_counsel' => env('AUDIT_KEY_LEGAL'),
            'regulator' => env('AUDIT_KEY_REGULATOR'),
            'user_recovery' => 'user-specific',
        ],
        'threshold' => 3, // 3-of-5 required
    ],

    'sanctions' => [
        'lists' => ['OFAC', 'EU', 'UN'],
        'update_frequency' => 'daily',
        'list_hash_url' => env('SANCTIONS_LIST_HASH_URL'),
    ],
];

// config/commerce.php
return [
    'settlement' => [
        'instant_max_amount' => 10000,
        'batch_time' => '00:00', // UTC
        'min_batch_amount' => 100,
    ],

    'fees' => [
        'default_rate' => 0.01, // 1%
        'privacy_premium' => 0.002, // +0.2% for shielded
        'min_fee' => 0.01, // $0.01 minimum
    ],

    'qr' => [
        'expiry_minutes' => 15,
        'protocol_version' => 1,
    ],

    'webhooks' => [
        'timeout' => 10,
        'retries' => 3,
        'events' => [
            'payment.received',
            'payment.confirmed',
            'payment.failed',
            'settlement.completed',
        ],
    ],
];

// config/trustcert.php
return [
    'contract_address' => env('TRUSTCERT_CONTRACT_ADDRESS'),
    'network' => env('TRUSTCERT_NETWORK', 'polygon'),

    'types' => [
        'PERSONAL_TRUST' => [
            'validity_days' => 365,
            'fee_usd' => 50,
            'required_kyc_level' => 'enhanced',
        ],
        'BUSINESS_TRUST' => [
            'validity_days' => 730,
            'fee_usd' => 500,
            'required_kyc_level' => 'full',
            'requires_kyb' => true,
        ],
        'DUAL_USE_EXPORT' => [
            'validity_days' => 180,
            'fee_usd' => 1000,
            'requires_government_check' => true,
        ],
        'ACCREDITED_INVESTOR' => [
            'validity_days' => 365,
            'fee_usd' => 200,
            'requires_financial_verification' => true,
        ],
        'WHITE_HAT' => [
            'validity_days' => 365,
            'fee_usd' => 100,
            'requires_technical_assessment' => true,
        ],
    ],

    'key_holders' => [
        // Same as audit vault
    ],
];

// config/keymanagement.php
return [
    'shamir' => [
        'total_shards' => 3,
        'threshold' => 2,
    ],

    'hsm' => [
        'provider' => env('HSM_PROVIDER', 'aws'), // aws, azure, gcp
        'key_id' => env('HSM_KEY_ID'),
        'region' => env('HSM_REGION'),
    ],

    'recovery' => [
        'backup_providers' => ['icloud', 'google_drive'],
        'encryption_algorithm' => 'AES-256-GCM',
        'key_derivation' => 'PBKDF2',
        'iterations' => 100000,
    ],
];
```

### 5.2 Environment Variables

```bash
# Privacy Layer
PRIVACY_POOL_ADDRESS=0x...
PRIVACY_POOL_NETWORK=polygon
SANCTIONS_LIST_HASH_URL=https://...

# Commerce
COMMERCE_WEBHOOK_SECRET=...

# TrustCert
TRUSTCERT_CONTRACT_ADDRESS=0x...
TRUSTCERT_NETWORK=polygon

# Key Management
HSM_PROVIDER=aws
HSM_KEY_ID=arn:aws:kms:...
HSM_REGION=us-east-1

# Audit Vault Keys (Shamir shards for decryption)
AUDIT_KEY_FINAEGIS=...
AUDIT_KEY_AUDITOR=...
AUDIT_KEY_LEGAL=...
AUDIT_KEY_REGULATOR=...
```

---

## 6. External Integrations

### 6.1 Required Integrations

| Integration | Purpose | Priority |
|-------------|---------|----------|
| **snarkjs** | ZK proof generation | Critical |
| **RAILGUN SDK** | Privacy pool contracts | Critical |
| **AWS KMS / Azure Key Vault** | HSM for key shards | Critical |
| **Polygon RPC** | Blockchain interaction | Critical |
| **The Graph** | Blockchain indexing | High |
| **Arweave** | Credential storage | Medium |
| **IPFS/Pinata** | Certificate metadata | Medium |

### 6.2 Composer Packages

```bash
# ZK Proofs
composer require --dev nicksailor/snarkjs-php  # or FFI wrapper

# Blockchain
# (Already have web3-php)

# HSM
composer require aws/aws-sdk-php
composer require microsoft/azure-storage-blob

# Shamir's Secret Sharing
composer require shamirs/secret-sharing

# Additional encryption
composer require paragonie/sodium_compat
```

---

## 7. Smart Contracts

### 7.1 TrustCertSBT.sol

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC721/ERC721.sol";
import "@openzeppelin/contracts/access/AccessControl.sol";

contract TrustCertSBT is ERC721, AccessControl {
    bytes32 public constant ISSUER_ROLE = keccak256("ISSUER_ROLE");

    enum CertType { PERSONAL_TRUST, BUSINESS_TRUST, DUAL_USE_EXPORT, ACCREDITED_INVESTOR, WHITE_HAT }
    enum Status { PENDING, ACTIVE, SUSPENDED, REVOKED, EXPIRED }

    struct Certificate {
        CertType certType;
        uint256 issuedAt;
        uint256 expiresAt;
        bytes32 credentialHash;
        Status status;
        string metadataURI;
    }

    mapping(uint256 => Certificate) public certificates;
    uint256 private _tokenIdCounter;

    event CertificateIssued(uint256 indexed tokenId, address indexed holder, CertType certType);
    event CertificateRevoked(uint256 indexed tokenId, string reason);

    constructor() ERC721("FinAegis TrustCert", "FATC") {
        _grantRole(DEFAULT_ADMIN_ROLE, msg.sender);
        _grantRole(ISSUER_ROLE, msg.sender);
    }

    function issue(
        address to,
        CertType certType,
        uint256 validityDays,
        bytes32 credentialHash,
        string memory metadataURI
    ) external onlyRole(ISSUER_ROLE) returns (uint256) {
        uint256 tokenId = _tokenIdCounter++;

        certificates[tokenId] = Certificate({
            certType: certType,
            issuedAt: block.timestamp,
            expiresAt: block.timestamp + (validityDays * 1 days),
            credentialHash: credentialHash,
            status: Status.ACTIVE,
            metadataURI: metadataURI
        });

        _safeMint(to, tokenId);
        emit CertificateIssued(tokenId, to, certType);

        return tokenId;
    }

    function revoke(uint256 tokenId, string memory reason) external onlyRole(ISSUER_ROLE) {
        require(_exists(tokenId), "Token does not exist");
        certificates[tokenId].status = Status.REVOKED;
        emit CertificateRevoked(tokenId, reason);
    }

    function verify(uint256 tokenId) external view returns (bool isValid, CertType certType, uint256 expiresAt) {
        if (!_exists(tokenId)) return (false, CertType.PERSONAL_TRUST, 0);

        Certificate memory cert = certificates[tokenId];
        isValid = cert.status == Status.ACTIVE && block.timestamp < cert.expiresAt;
        certType = cert.certType;
        expiresAt = cert.expiresAt;
    }

    // Soulbound: disable transfers
    function _beforeTokenTransfer(
        address from,
        address to,
        uint256 tokenId,
        uint256 batchSize
    ) internal virtual override {
        require(from == address(0) || to == address(0), "Soulbound: transfers disabled");
        super._beforeTokenTransfer(from, to, tokenId, batchSize);
    }

    function supportsInterface(bytes4 interfaceId) public view virtual override(ERC721, AccessControl) returns (bool) {
        return super.supportsInterface(interfaceId);
    }
}
```

---

## 8. Implementation Phases

### Phase 1: Key Management Foundation (Weeks 1-3)
- [ ] Implement ShamirService
- [ ] AWS KMS / Azure Key Vault integration
- [ ] Key shard storage models and migrations
- [ ] Recovery flow implementation
- [ ] API endpoints for shard management

### Phase 2: Privacy Domain (Weeks 4-8)
- [ ] Privacy models and migrations
- [ ] ZK prover service (snarkjs integration)
- [ ] RAILGUN SDK integration
- [ ] Audit vault with multi-party encryption
- [ ] Proof of Innocence service
- [ ] Privacy API endpoints
- [ ] WebSocket events for privacy transactions

### Phase 3: Commerce Domain (Weeks 9-12)
- [ ] Merchant models and registration
- [ ] Payment request QR generation
- [ ] Payment execution (public + private)
- [ ] Settlement engine
- [ ] Webhook delivery system
- [ ] Merchant SDK (TypeScript)
- [ ] Commerce API endpoints

### Phase 4: TrustCert Domain (Weeks 13-18)
- [ ] Certificate models and migrations
- [ ] Application workflow
- [ ] Document verification integration
- [ ] Background check integration
- [ ] Smart contract deployment (testnet)
- [ ] Blockchain mint service
- [ ] ZK credential proofs
- [ ] TrustCert API endpoints
- [ ] Smart contract deployment (mainnet)

### Phase 5: Integration & Testing (Weeks 19-22)
- [ ] End-to-end integration tests
- [ ] Security audit preparation
- [ ] Performance optimization
- [ ] Documentation

### Phase 6: Launch (Weeks 23-24)
- [ ] Security audit fixes
- [ ] Mainnet deployment
- [ ] Monitoring setup
- [ ] Launch support

---

## 9. Testing Strategy

### 9.1 Test Categories

```bash
# Unit Tests
tests/Unit/Domain/Privacy/
tests/Unit/Domain/Commerce/
tests/Unit/Domain/TrustCert/
tests/Unit/Domain/KeyManagement/

# Feature Tests
tests/Feature/Privacy/
tests/Feature/Commerce/
tests/Feature/TrustCert/

# Integration Tests
tests/Integration/ZkProver/
tests/Integration/Blockchain/
tests/Integration/HSM/

# Contract Tests (Foundry)
contracts/test/TrustCertSBT.t.sol
contracts/test/ShieldPool.t.sol
```

### 9.2 Test Coverage Targets

| Domain | Target Coverage |
|--------|-----------------|
| Privacy | 90% |
| Commerce | 85% |
| TrustCert | 85% |
| KeyManagement | 95% |

---

## 10. Security Considerations

### 10.1 Audit Requirements

| Component | Auditor Type | Timeline |
|-----------|--------------|----------|
| Smart Contracts | Trail of Bits / OpenZeppelin | Week 19-20 |
| ZK Circuits | Specialized ZK auditor | Week 19-20 |
| Key Management | Cryptography auditor | Week 18 |
| Overall Application | Penetration testing | Week 21 |

### 10.2 Security Controls

- [ ] HSM integration for all auth shards
- [ ] Multi-party encryption for audit vault
- [ ] Rate limiting on all privacy endpoints
- [ ] Nullifier registry to prevent double-spend
- [ ] Timelock on smart contract upgrades
- [ ] Emergency pause functionality
- [ ] Sanctions screening before any transaction

---

## Appendix A: File Checklist

```
[ ] app/Domain/Privacy/Models/ShieldedBalance.php
[ ] app/Domain/Privacy/Models/ShieldTransaction.php
[ ] app/Domain/Privacy/Models/PrivacyProof.php
[ ] app/Domain/Privacy/Models/AuditVaultEntry.php
[ ] app/Domain/Privacy/Services/PrivacyPoolService.php
[ ] app/Domain/Privacy/Services/ZkProverService.php
[ ] app/Domain/Privacy/Services/ProofOfInnocenceService.php
[ ] app/Domain/Privacy/Services/AuditVaultService.php
[ ] app/Domain/Commerce/Models/Merchant.php
[ ] app/Domain/Commerce/Models/PaymentRequest.php
[ ] app/Domain/Commerce/Models/StablecoinPayment.php
[ ] app/Domain/Commerce/Services/PaymentExecutionService.php
[ ] app/Domain/Commerce/Services/SettlementService.php
[ ] app/Domain/TrustCert/Models/Certificate.php
[ ] app/Domain/TrustCert/Models/CertificateApplication.php
[ ] app/Domain/TrustCert/Services/BlockchainMintService.php
[ ] app/Domain/TrustCert/Services/ZkCredentialService.php
[ ] app/Domain/KeyManagement/Services/ShamirService.php
[ ] app/Domain/KeyManagement/HSM/HsmIntegrationService.php
[ ] app/Http/Controllers/Api/PrivacyController.php
[ ] app/Http/Controllers/Api/CommerceController.php
[ ] app/Http/Controllers/Api/TrustCertController.php
[ ] config/privacy.php
[ ] config/commerce.php
[ ] config/trustcert.php
[ ] config/keymanagement.php
[ ] contracts/TrustCertSBT.sol
[ ] database/migrations/*_create_privacy_tables.php
[ ] database/migrations/*_create_commerce_tables.php
[ ] database/migrations/*_create_trustcert_tables.php
```

---

*Document Version: 1.0*
*Last Updated: February 2026*
*Author: FinAegis Architecture Team*
