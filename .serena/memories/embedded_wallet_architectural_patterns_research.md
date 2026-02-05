# Embedded Wallet Architectural Patterns Research

**Research Date**: February 1, 2026  
**Purpose**: Inform FinAegis mobile wallet architecture based on industry best practices  
**Status**: ✅ Complete

---

## Executive Summary

This document analyzes four critical architectural patterns for embedded wallet infrastructure:

1. **Privy & Turnkey** - Leading embedded wallet providers using Shamir's Secret Sharing (SSS) and secure enclaves
2. **Railgun & Aztec** - Privacy-preserving payment protocols with compliance features
3. **MPC/TSS** - Multi-Party Computation for distributed key management
4. **Passkeys/FIDO2** - Phishing-resistant authentication for wallets
5. **Zero-Knowledge Proofs** - Privacy-preserving KYC and authentication

---

## 1. Privy Embedded Wallet Architecture

### Core Technology Stack

| Component | Technology | Purpose |
|-----------|-----------|---------|
| Key Management | Shamir's Secret Sharing (SSS) | Split private keys into 3 shards |
| Security | Trusted Execution Environments (TEEs) | Hardware-isolated key reconstruction |
| Authentication | Email, SMS, Social, Passkeys, Wallets | Multi-method onboarding |
| Multi-Chain | EVM, Solana, Bitcoin, TRON, Stellar | Universal blockchain support |

### Key Sharding Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                  PRIVY KEY SHARDING MODEL                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Private Key (never exists as whole)                            │
│         │                                                       │
│         ├─▶ Device Shard ──────┐                               │
│         │   (User's device)    │                               │
│         │                      │                               │
│         ├─▶ Auth Shard ────────┼──▶ Assembled in TEE          │
│         │   (Privy backend)    │    (Sign transaction)         │
│         │                      │                               │
│         └─▶ Recovery Shard ────┘                               │
│             (User backup)                                       │
│                                                                 │
│  Requirements:                                                  │
│  • Device Shard + Auth Shard = Active signing                  │
│  • Recovery Shard = Account recovery across devices            │
│  • Keys reconstructed ONLY in TEE, never exposed               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Authentication Flow

```php
// Privy-inspired authentication pattern
class EmbeddedWalletAuthService
{
    public function authenticate(User $user, string $method): WalletAccess
    {
        // 1. User authenticates with chosen method (email, social, passkey)
        $authToken = $this->authProvider->verify($user, $method);
        
        // 2. Request auth shard from backend with short-lived token
        $authShard = $this->shardService->getAuthShard($user->id, $authToken);
        
        // 3. Combine with device shard in TEE
        $wallet = $this->teeService->reconstructWallet([
            'device_shard' => $this->deviceStorage->getDeviceShard(),
            'auth_shard' => $authShard,
        ]);
        
        // 4. Return wallet access (time-limited)
        return new WalletAccess($wallet, expiresIn: 300); // 5 minutes
    }
}
```

### Security Guarantees

| Guarantee | Implementation |
|-----------|---------------|
| **Non-Custodial** | Neither Privy nor app sees full private key |
| **User Control** | Keys only reconstructed in user-controlled TEE |
| **Recovery** | Recovery shard enables cross-device access |
| **Expiring Access** | Auth tokens expire quickly (seconds to minutes) |
| **Policy Engine** | Programmable signing rules (biometric, device restriction, limits) |

### Programmable Policies

```typescript
// Privy policy examples
const walletPolicies = {
  requireBiometric: true,
  allowedDevices: ['device-uuid-1', 'device-uuid-2'],
  transactionLimits: {
    daily: '10000 USD',
    perTransaction: '5000 USD',
  },
  allowedNetworks: ['ethereum', 'polygon', 'base'],
  requireApproval: (tx) => tx.value > ethers.parseEther('1.0'),
};
```

**Sources**:
- [Privy Docs - Wallets Overview](https://docs.privy.io/wallets/overview)
- [Privy Blog - How Embedded Wallets Work](https://privy.io/blog/how-privy-embedded-wallets-work)
- [Privy Blog - Embedded Wallet Architecture Breakdown](https://privy.io/blog/embedded-wallet-architecture-breakdown)

---

## 2. Turnkey Non-Custodial Key Management

### Core Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                  TURNKEY ARCHITECTURE                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐ │
│  │              SECURE ENCLAVE (AWS Nitro / SGX)             │ │
│  │                                                           │ │
│  │  ┌─────────────┐    ┌──────────────┐    ┌─────────────┐ │ │
│  │  │   Private   │───▶│ Policy Engine│───▶│  Signature  │ │ │
│  │  │     Keys    │    │ (Customer-   │    │  Generation │ │ │
│  │  │ (Encrypted) │    │  Defined)    │    │             │ │ │
│  │  └─────────────┘    └──────────────┘    └─────────────┘ │ │
│  │         │                  │                    │        │ │
│  │         │                  │                    │        │ │
│  │         ▼                  ▼                    ▼        │ │
│  │   Never exits        All actions          Only valid    │ │
│  │   enclave           audited & logged     sigs exported  │ │
│  │                                                           │ │
│  └───────────────────────────────────────────────────────────┘ │
│                              ▲                                  │
│                              │                                  │
│  ┌───────────────────────────┴───────────────────────────────┐ │
│  │         API Authentication (Passkey, API Key)             │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Policy Engine Structure

```php
// Turnkey-inspired policy engine
class SecureEnclaveKeyService
{
    public function signTransaction(
        string $walletId,
        Transaction $tx,
        AuthCredential $credential
    ): SignedTransaction {
        // 1. Authenticate to enclave
        $session = $this->enclave->authenticate($credential);
        
        // 2. Load policies for wallet
        $policies = $this->policyStore->get($walletId);
        
        // 3. Evaluate all policies
        foreach ($policies as $policy) {
            if (!$policy->allows($tx, $session)) {
                throw new PolicyViolationException($policy->reason());
            }
        }
        
        // 4. Sign inside enclave (key never leaves)
        return $this->enclave->sign($walletId, $tx->hash());
    }
}
```

### Policy Types

| Policy Type | Example | Use Case |
|-------------|---------|----------|
| **Approval Workflow** | 2-of-3 signers required | Multi-sig governance |
| **Amount Limits** | Max $10,000 per day | Risk management |
| **Destination Allowlist** | Only approved addresses | Anti-fraud |
| **Time-Based** | Trading hours 9am-5pm EST | Operational controls |
| **Velocity Limits** | Max 5 transactions/hour | Rate limiting |
| **Geographic** | Block sanctioned countries | Compliance |

### Remote Attestation

```bash
# Turnkey attestation verification
curl https://api.turnkey.com/attestation/verify \
  -H "X-Stamp: $(generate_stamp)" \
  --data '{
    "enclave_measurement": "sha256:abc123...",
    "pcr_values": ["pcr0", "pcr1", "pcr2"]
  }'
```

**Benefits**:
- **Verifiable Security**: Cryptographic proof of code running in enclave
- **Reproducible Builds**: Deterministic binary compilation
- **Transparency**: Anyone can verify the enclave code

**Sources**:
- [Turnkey Docs - Non-Custodial Key Management](https://docs.turnkey.com/security/non-custodial-key-mgmt)
- [Turnkey Whitepaper - Key Management Re-Imagined](https://whitepaper.turnkey.com/principles)
- [Turnkey Blog - 2025 Wrapped](https://www.turnkey.com/blog/2025-turnkey-crypto-wallet-infrastructure)

---

## 3. Privacy-Preserving Payment Protocols

### RAILGUN Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                  RAILGUN PRIVACY SYSTEM                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Public Blockchain (Ethereum, Polygon, BSC, Arbitrum)          │
│         │                                                       │
│         ▼                                                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │           RAILGUN Smart Contracts (Layer 1)             │   │
│  │                                                         │   │
│  │  ┌─────────────────────────────────────────────────┐   │   │
│  │  │         Private Balances (Encrypted UTXOs)      │   │   │
│  │  │  • Shield (Deposit): Public → Private           │   │   │
│  │  │  • Transfer: Private → Private (zk-SNARK)       │   │   │
│  │  │  • Unshield (Withdraw): Private → Public        │   │   │
│  │  └─────────────────────────────────────────────────┘   │   │
│  │                                                         │   │
│  │  ┌─────────────────────────────────────────────────┐   │   │
│  │  │        Broadcaster Network (Gas Relayers)       │   │   │
│  │  │  • User sends encrypted tx to broadcaster       │   │   │
│  │  │  • Broadcaster submits to blockchain            │   │   │
│  │  │  • Tx appears to originate from broadcaster     │   │   │
│  │  └─────────────────────────────────────────────────┘   │   │
│  │                                                         │   │
│  │  ┌─────────────────────────────────────────────────┐   │   │
│  │  │         Proof of Innocence (Compliance)         │   │   │
│  │  │  • Prove funds ≠ sanctioned addresses           │   │   │
│  │  │  • Without revealing full transaction history   │   │   │
│  │  │  • Regulatory compliance meets privacy          │   │   │
│  │  └─────────────────────────────────────────────────┘   │   │
│  │                                                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Key Privacy Mechanisms

| Mechanism | Technology | Privacy Guarantee |
|-----------|-----------|-------------------|
| **Encrypted UTXOs** | zk-SNARKs | Amounts and recipients hidden |
| **Stealth Addresses** | ECDH key agreement | One-time addresses per transaction |
| **Broadcasters** | Gas relay network | Sender IP/identity unlinkable |
| **Merkle Trees** | Accumulator structure | Transaction set membership proofs |

### Proof of Innocence Flow

```php
// RAILGUN-inspired compliance check
class PrivacyComplianceService
{
    public function generateProofOfInnocence(
        ShieldedTransaction $tx
    ): ComplianceProof {
        // 1. User generates ZK proof that their transaction
        //    does NOT involve sanctioned addresses
        $proof = $this->zkProver->prove([
            'statement' => 'tx_not_from_sanctioned_set',
            'public_inputs' => [
                'tx_commitment' => $tx->commitment,
                'sanctioned_addresses_merkle_root' => $this->getSanctionedRoot(),
            ],
            'private_inputs' => [
                'tx_history' => $this->wallet->getPrivateHistory(),
                'source_addresses' => $this->wallet->getSourceAddresses(),
            ],
        ]);
        
        // 2. Proof reveals NOTHING about actual transaction history
        // 3. Regulator can verify proof on-chain or off-chain
        return new ComplianceProof($proof, expiresAt: now()->addDays(90));
    }
}
```

### Aztec Comparison

| Feature | RAILGUN | Aztec |
|---------|---------|-------|
| **Architecture** | Layer 1 middleware | Layer 2 ZK-Rollup |
| **Privacy Timing** | Real-time | Batch (rollup intervals) |
| **DeFi Integration** | Direct (Uniswap, Aave, etc.) | Limited |
| **Multi-Chain** | 4+ chains | Ethereum-focused |
| **Programmability** | Smart contract calls | Encrypted programmability |

**Sources**:
- [RAILGUN Docs - Privacy System](https://docs.railgun.org/wiki/learn/privacy-system)
- [RAILGUN Medium - Proof of Innocence](https://medium.com/@Railgun_Project/having-your-privacy-eating-it-too-railgun-proof-of-innocence-efcba557aac4)
- [Nansen Research - Aztec Network](https://research.nansen.ai/articles/aztec-network-and-the-role-of-privacy-protocols)

---

## 4. Multi-Party Computation (MPC) & Threshold Signatures

### MPC Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│            MPC THRESHOLD SIGNATURE SCHEME (TSS)                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Private Key (NEVER reconstructed)                              │
│         │                                                       │
│         │  Distributed Key Generation (DKG)                    │
│         │                                                       │
│         ├─▶ Key Share 1 ──┐                                    │
│         │   (User device) │                                    │
│         │                 │                                    │
│         ├─▶ Key Share 2 ──┼──▶ t-of-n Threshold Signing       │
│         │   (Cloud HSM)   │    (e.g., 2-of-3 required)         │
│         │                 │                                    │
│         └─▶ Key Share 3 ──┘                                    │
│             (Recovery service)                                  │
│                                                                 │
│  Signing Process:                                               │
│  1. Transaction proposed                                        │
│  2. Each party generates signature share                        │
│  3. Shares combined to create full signature                    │
│  4. Private key NEVER exists in one place                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### MPC vs Shamir's Secret Sharing

| Aspect | MPC (Threshold Signatures) | Shamir's Secret Sharing |
|--------|---------------------------|-------------------------|
| **Key Reconstruction** | Never reconstructed | Temporarily reconstructed in TEE |
| **Signing** | Distributed signature generation | Requires key reconstruction |
| **Security** | No single point of compromise | TEE must be trusted |
| **Flexibility** | Dynamic threshold changes | Fixed at key creation |
| **Performance** | Slower (multi-round protocol) | Faster (single-party signing) |

### Implementation Pattern

```php
// MPC-inspired distributed signing
class ThresholdSignatureService
{
    private array $signers; // Key share holders
    private int $threshold; // Minimum required signers
    
    public function initiateSignature(
        Transaction $tx,
        array $participatingSigners
    ): SigningSession {
        if (count($participatingSigners) < $this->threshold) {
            throw new InsufficientSignersException();
        }
        
        // 1. Generate session ID
        $sessionId = Str::uuid();
        
        // 2. Each signer generates their signature share
        $shares = [];
        foreach ($participatingSigners as $signer) {
            $shares[] = $signer->generateSignatureShare($tx, $sessionId);
        }
        
        // 3. Combine shares (no key reconstruction!)
        $signature = $this->combineShares($shares);
        
        // 4. Verify signature
        if (!$this->verify($signature, $tx, $this->publicKey)) {
            throw new InvalidSignatureException();
        }
        
        return new SigningSession($sessionId, $signature);
    }
    
    private function combineShares(array $shares): Signature
    {
        // ECDSA threshold signature combination
        // Uses Lagrange interpolation in the exponent
        // Mathematical detail: https://eprint.iacr.org/2020/852.pdf
        return ThresholdECDSA::combine($shares);
    }
}
```

### Advantages for Embedded Wallets

| Benefit | Description |
|---------|-------------|
| **Multi-Chain Native** | Works with any ECDSA/EdDSA chain (unlike smart contract multisig) |
| **No On-Chain Footprint** | Indistinguishable from single-sig wallets |
| **Dynamic Policies** | Change threshold without on-chain transaction |
| **Enterprise-Grade** | Used by Fireblocks, Coinbase, Binance |

**Sources**:
- [MetaMask Developer Docs - MPC](https://docs.metamask.io/embedded-wallets/features/mpc/)
- [Fireblocks - What is MPC](https://www.fireblocks.com/what-is-mpc)
- [NIST - Multi-Party Threshold Cryptography](https://csrc.nist.gov/projects/threshold-cryptography)

---

## 5. Passkeys & FIDO2 Authentication

### FIDO2 Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                  PASSKEY AUTHENTICATION FLOW                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  User Device                     FinAegis Backend               │
│  ┌────────────┐                  ┌────────────┐                │
│  │            │   1. Challenge   │            │                │
│  │   Mobile   │◀─────────────────│  API Server│                │
│  │    App     │                  │            │                │
│  │            │   2. User auth   └────────────┘                │
│  │            │      (biometric)       │                       │
│  │            │                        │                       │
│  │  ┌──────┐  │                        │                       │
│  │  │Secure│  │   3. Sign challenge    │                       │
│  │  │Enclave│  │      with private key  │                       │
│  │  │(TEE) │  │      (never exported)  │                       │
│  │  └──────┘  │                        │                       │
│  │            │   4. Signed response   │                       │
│  │            │───────────────────────▶│                       │
│  └────────────┘                        │                       │
│                                        ▼                       │
│                               ┌────────────┐                   │
│                               │  Verify    │                   │
│                               │  Signature │                   │
│                               │  (public   │                   │
│                               │   key)     │                   │
│                               └────────────┘                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### WebAuthn Registration

```php
// FIDO2 passkey registration
class PasskeyRegistrationService
{
    public function register(User $user, DeviceInfo $device): PasskeyCredential
    {
        // 1. Generate challenge
        $challenge = random_bytes(32);
        
        Cache::put(
            "passkey_challenge:{$user->id}",
            $challenge,
            now()->addMinutes(5)
        );
        
        // 2. Return registration options to client
        return new PasskeyOptions([
            'challenge' => base64_encode($challenge),
            'rp' => [
                'name' => 'FinAegis',
                'id' => 'finaegis.com',
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7], // ES256 (ECDSA P-256)
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform', // Device-bound
                'userVerification' => 'required', // Biometric required
                'residentKey' => 'required', // Passkey storage
            ],
            'timeout' => 60000,
        ]);
    }
    
    public function verify(
        User $user,
        array $credential
    ): MobileDevice {
        // 1. Verify challenge
        $expectedChallenge = Cache::pull("passkey_challenge:{$user->id}");
        
        if (!hash_equals($expectedChallenge, base64_decode($credential['challenge']))) {
            throw new InvalidChallengeException();
        }
        
        // 2. Verify signature using stored public key
        $publicKey = base64_decode($credential['publicKey']);
        
        if (!$this->verifySignature($credential, $publicKey)) {
            throw new InvalidSignatureException();
        }
        
        // 3. Store credential for future authentications
        return MobileDevice::create([
            'user_id' => $user->id,
            'device_id' => $credential['credentialId'],
            'passkey_public_key' => $credential['publicKey'],
            'passkey_credential_id' => $credential['credentialId'],
            'authenticator_type' => 'platform', // Face ID / Touch ID
        ]);
    }
}
```

### Authentication Flow

```typescript
// Mobile app passkey authentication
async function authenticateWithPasskey(userId: string) {
  // 1. Request challenge from server
  const { challenge, allowCredentials } = await api.post('/auth/passkey/challenge', {
    user_id: userId,
  });
  
  // 2. Get user to authenticate (Face ID / Touch ID)
  const credential = await navigator.credentials.get({
    publicKey: {
      challenge: base64ToArrayBuffer(challenge),
      allowCredentials: allowCredentials.map(cred => ({
        id: base64ToArrayBuffer(cred.id),
        type: 'public-key',
      })),
      userVerification: 'required',
      timeout: 60000,
    },
  });
  
  // 3. Send signed response to server
  const { access_token } = await api.post('/auth/passkey/verify', {
    credential: {
      id: credential.id,
      rawId: arrayBufferToBase64(credential.rawId),
      response: {
        authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
        clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
        signature: arrayBufferToBase64(credential.response.signature),
      },
      type: credential.type,
    },
  });
  
  return access_token;
}
```

### Regulatory Momentum (2026)

| Regulation | Status | Impact |
|------------|--------|--------|
| **NIST SP 800-63-4** | Finalized July 2025 | Passkeys = AAL2 (Authenticator Assurance Level 2) |
| **eIDAS 2.0 (EU)** | Enforced May 2024 | Digital identity wallets mandated |
| **US Federal FIDO2 Mandate** | Active 2025+ | All federal agencies require phishing-resistant MFA |

**Sources**:
- [FIDO Alliance - Passkeys](https://fidoalliance.org/passkeys/)
- [Microsoft Entra ID - FIDO2 Passkeys](https://learn.microsoft.com/en-us/entra/identity/authentication/concept-authentication-passkeys-fido2)
- [MojoAuth - Passkeys Handbook 2025](https://mojoauth.com/white-papers/passkeys-passwordless-authentication-handbook/)

---

## 6. Zero-Knowledge Proofs for Privacy-Preserving KYC

### zkKYC Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│              ZERO-KNOWLEDGE KYC SYSTEM                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │          User's Self-Custody Wallet                     │   │
│  │                                                         │   │
│  │  ┌─────────────────────────────────────────────────┐   │   │
│  │  │       Verifiable Credentials (VCs)              │   │   │
│  │  │  • Full Name: "Alice Smith"                     │   │   │
│  │  │  • Date of Birth: "1990-05-15"                  │   │   │
│  │  │  • Country: "Germany"                           │   │   │
│  │  │  • KYC Level: "Tier 2"                          │   │   │
│  │  │  • Issuer: gov.germany.identity                 │   │   │
│  │  │  • Signature: <signed by issuer>                │   │   │
│  │  └─────────────────────────────────────────────────┘   │   │
│  │                                                         │   │
│  │  ┌─────────────────────────────────────────────────┐   │   │
│  │  │       ZK Proof Generator                        │   │   │
│  │  │  • Input: Full VC                               │   │   │
│  │  │  • Prove: "age > 18" without revealing DOB      │   │   │
│  │  │  • Prove: "country = EU" without exact country  │   │   │
│  │  │  • Prove: "KYC tier >= 2" without full details  │   │   │
│  │  └─────────────────────────────────────────────────┘   │   │
│  │                                                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │          FinAegis Verifier                              │   │
│  │  • Receives: ZK proof                                   │   │
│  │  • Verifies: Proof validity (cryptographic)             │   │
│  │  • Learns: ONLY the proved statement                    │   │
│  │  • Example: "User is 18+, EU resident, KYC Tier 2"     │   │
│  │  • Never sees: Name, DOB, exact country, ID documents   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Privacy Reduction

**Traditional KYC Data Exposed**:
- Full name
- Full date of birth
- Full address
- ID document scans
- Selfie photos
- Phone number
- Email address

**zkKYC Data Exposed**:
- Proof: "age > 18" ✓
- Proof: "country in EU" ✓
- Proof: "KYC tier >= 2" ✓

**Data Reduction**: ~97% less personal data exposed

### Implementation Pattern

```php
// Zero-knowledge KYC verification
class ZkKycService
{
    public function verifyKycProof(
        string $userId,
        array $zkProof,
        array $requiredClaims
    ): KycVerificationResult {
        // 1. Parse ZK proof
        $proof = json_decode($zkProof['proof']);
        $publicInputs = $zkProof['public_inputs'];
        
        // 2. Verify cryptographic proof
        if (!$this->zkVerifier->verify($proof, $publicInputs)) {
            throw new InvalidZkProofException();
        }
        
        // 3. Check required claims are satisfied
        foreach ($requiredClaims as $claim) {
            if (!$this->claimSatisfied($claim, $publicInputs)) {
                throw new InsufficientKycException($claim);
            }
        }
        
        // 4. Store verification result (NOT the proof details)
        $verification = KycVerification::create([
            'user_id' => $userId,
            'verified_at' => now(),
            'claims_verified' => $requiredClaims,
            'issuer_did' => $publicInputs['issuer_did'],
            'expires_at' => Carbon::parse($publicInputs['expiry']),
        ]);
        
        return new KycVerificationResult(
            verified: true,
            tier: $this->extractTier($publicInputs),
            expiresAt: $verification->expires_at,
        );
    }
}
```

### Selective Disclosure Examples

| Service Request | Traditional KYC | zkKYC |
|----------------|-----------------|-------|
| **Open bank account** | Full name, DOB, address, ID scan | Proof: "age > 18", "country = X", "identity verified by trusted issuer" |
| **Trade crypto > €1000** | All above + selfie, source of funds | Proof: "KYC tier >= 2", "age > 18" |
| **Access 18+ content** | Full DOB verification | Proof: "age >= 18" (no DOB revealed) |
| **Geographic restriction** | Full address | Proof: "country in [allowed_list]" |

### Technical Performance (2026)

| Metric | Value | Technology |
|--------|-------|-----------|
| **Proof Generation** | < 1 second | Browser-based WASM |
| **Proof Verification** | < 100ms | Server-side |
| **Proof Size** | 128-512 bytes | Groth16, PLONK |
| **Hardware Acceleration** | GPU, FPGA, ASIC | Mainstream adoption |

### Real-World Implementations (2026)

| Project | Focus | Status |
|---------|-------|--------|
| **Galactica Network** | zkKYC for DeFi | Mainnet live |
| **zkPass** | Private data verification | Production |
| **Polygon ID** | Self-sovereign identity | Enterprise adoption |
| **EUDI Wallet (EU)** | Government digital identity | Mandated deployment |

**Sources**:
- [Zyphe - Zero-Knowledge Proof in KYC](https://www.zyphe.com/resources/blog/what-is-zero-knowledge-proof-in-kyc-verification)
- [Cryptonium - Zero-Knowledge Sovereignty 2026](https://cryptonium.cloud/articles/zero-knowledge-sovereignty-digital-identity-2026)
- [Galactica Network - zkKYC Docs](https://docs.galactica.com/galactica-developer-documentation/galactica-concepts/zero-knowledge-kyc)
- [zkPass - Private Data Protocol](https://zkpass.org/)

---

## 7. On-Chain Certification with Soulbound Tokens

### SBT Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│              SOULBOUND TOKEN CERTIFICATION                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Issuer (University, Government, DAO)                           │
│         │                                                       │
│         ▼                                                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │           SBT Smart Contract (ERC-5114)                 │   │
│  │                                                         │   │
│  │  function mint(address soul, Attestation memory data)  │   │
│  │  function revoke(uint256 tokenId)                      │   │
│  │  function isValid(uint256 tokenId) returns (bool)      │   │
│  │                                                         │   │
│  │  Properties:                                            │   │
│  │  • Non-transferable (bound to address)                 │   │
│  │  • Publicly verifiable                                  │   │
│  │  • Revocable by issuer                                 │   │
│  │  • Expirable (optional)                                │   │
│  │                                                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│                              ▼                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │          Recipient's Wallet (Soul)                      │   │
│  │  • 0x123...abc                                          │   │
│  │  • SBT #1: KYC Tier 2 (Expires: 2027-01-01)           │   │
│  │  • SBT #2: Accredited Investor                         │   │
│  │  • SBT #3: DAO Member (Governance rights)             │   │
│  │                                                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Use Cases for FinAegis

| Use Case | SBT Type | Benefit |
|----------|----------|---------|
| **KYC Verification** | KYC tier attestation | On-chain proof without revealing PII |
| **Accredited Investor** | Investment eligibility | Access to premium products |
| **Compliance Training** | Training completion | Regulatory requirement tracking |
| **Credit Score** | Creditworthiness band | DeFi lending without full credit report |
| **Governance Rights** | DAO membership | Voting power, proposal rights |

### Implementation

```solidity
// Soulbound Token for KYC attestation
contract FinAegisKycSBT is ERC5114 {
    struct KycAttestation {
        uint8 tier; // 1-4
        string jurisdiction; // ISO 3166-1 alpha-2
        uint256 issuedAt;
        uint256 expiresAt;
        bytes32 verificationHash; // Privacy-preserving proof
    }
    
    mapping(uint256 => KycAttestation) public attestations;
    mapping(address => bool) public authorizedIssuers;
    
    function mint(
        address soul,
        KycAttestation memory attestation
    ) external onlyAuthorizedIssuer {
        require(attestation.expiresAt > block.timestamp, "Already expired");
        
        uint256 tokenId = uint256(keccak256(abi.encodePacked(soul, block.timestamp)));
        
        _mint(soul, tokenId);
        attestations[tokenId] = attestation;
        
        emit KycAttested(soul, tokenId, attestation.tier);
    }
    
    function isValid(uint256 tokenId) public view returns (bool) {
        if (!_exists(tokenId)) return false;
        
        KycAttestation memory att = attestations[tokenId];
        return att.expiresAt > block.timestamp;
    }
    
    function getKycTier(address soul) external view returns (uint8) {
        // Find highest valid tier for this address
        uint8 maxTier = 0;
        
        // Iterate through tokens (in production, use indexing)
        for (uint256 i = 0; i < balanceOf(soul); i++) {
            uint256 tokenId = tokenOfOwnerByIndex(soul, i);
            if (isValid(tokenId)) {
                uint8 tier = attestations[tokenId].tier;
                if (tier > maxTier) {
                    maxTier = tier;
                }
            }
        }
        
        return maxTier;
    }
}
```

### Laravel Integration

```php
// Check on-chain SBT for KYC status
class SoulboundKycService
{
    public function verifyKycFromBlockchain(string $walletAddress): KycStatus
    {
        // 1. Query SBT contract
        $contract = $this->web3->contract($this->kycSbtAbi, config('blockchain.kyc_sbt_address'));
        
        $tier = $contract->call('getKycTier', [$walletAddress]);
        
        if ($tier === 0) {
            return new KycStatus(verified: false, tier: null);
        }
        
        // 2. Get token details
        $balance = $contract->call('balanceOf', [$walletAddress]);
        
        for ($i = 0; $i < $balance; $i++) {
            $tokenId = $contract->call('tokenOfOwnerByIndex', [$walletAddress, $i]);
            $isValid = $contract->call('isValid', [$tokenId]);
            
            if ($isValid) {
                $attestation = $contract->call('attestations', [$tokenId]);
                
                return new KycStatus(
                    verified: true,
                    tier: $tier,
                    jurisdiction: $attestation['jurisdiction'],
                    expiresAt: Carbon::createFromTimestamp($attestation['expiresAt']),
                );
            }
        }
        
        return new KycStatus(verified: false, tier: null);
    }
}
```

**Sources**:
- [CoinGecko - Soulbound Tokens](https://www.coingecko.com/learn/soulbound-tokens-sbt)
- [Ethereum Research - Beyond SBTs for Verifiable Credentials](https://ethresear.ch/t/going-beyond-sbts-erc721-for-verifiable-credentials/14789)
- [Cube Exchange - What are Soulbound Tokens](https://www.cube.exchange/what-is/soulbound-token)

---

## 8. Recommendations for FinAegis Mobile Wallet

### Immediate Priorities (v2.2.0)

| Feature | Technology Choice | Rationale |
|---------|------------------|-----------|
| **Key Management** | Shamir's Secret Sharing + TEE | Balance of security and UX (Privy model) |
| **Authentication** | Passkeys (FIDO2) | Phishing-resistant, regulatory compliant |
| **Biometric Auth** | ECDSA P-256 + Secure Enclave | Already implemented, industry standard |
| **Session Management** | Short-lived tokens + device binding | Security best practice |

### Medium-Term Enhancements (v2.3.0)

| Feature | Technology Choice | Rationale |
|---------|------------------|-----------|
| **Privacy Payments** | RAILGUN integration | Compliant privacy for institutional clients |
| **zkKYC** | Polygon ID or Galactica | Privacy-preserving compliance |
| **SBT Attestations** | Custom ERC-5114 | On-chain verifiable credentials |
| **MPC Wallets** | Threshold signatures (2-of-3) | Enterprise multi-sig |

### Long-Term Vision (v2.4.0+)

| Feature | Technology Choice | Rationale |
|---------|------------------|-----------|
| **Cross-Chain MPC** | Universal threshold signatures | Multi-chain without rebuilding |
| **Decentralized Recovery** | Social recovery + MPC | No central recovery service |
| **Quantum-Resistant** | Post-quantum cryptography | Future-proofing |
| **Hardware Wallet Sync** | Ledger/Trezor as key share holder | Professional custody |

### Architecture Decision Matrix

```
┌─────────────────────────────────────────────────────────────────┐
│          FINAEGIS EMBEDDED WALLET ARCHITECTURE                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌───────────────────────────────────────────────────────────┐ │
│  │                   AUTHENTICATION LAYER                     │ │
│  │  • Passkeys (FIDO2) - Primary auth                        │ │
│  │  • Biometric (ECDSA P-256) - Device-bound                 │ │
│  │  • Email/SMS - Backup recovery                            │ │
│  └───────────────────────────────────────────────────────────┘ │
│                              │                                  │
│  ┌───────────────────────────▼───────────────────────────────┐ │
│  │                   KEY MANAGEMENT LAYER                     │ │
│  │  • Shamir's Secret Sharing (3 shards)                     │ │
│  │  • Device Shard (secure enclave)                          │ │
│  │  • Auth Shard (backend HSM)                               │ │
│  │  • Recovery Shard (encrypted cloud backup)               │ │
│  └───────────────────────────────────────────────────────────┘ │
│                              │                                  │
│  ┌───────────────────────────▼───────────────────────────────┐ │
│  │                   POLICY ENGINE LAYER                      │ │
│  │  • Transaction limits (amount, velocity)                  │ │
│  │  • Device allowlisting                                    │ │
│  │  • Geographic restrictions                                │ │
│  │  • Time-based controls                                    │ │
│  └───────────────────────────────────────────────────────────┘ │
│                              │                                  │
│  ┌───────────────────────────▼───────────────────────────────┐ │
│  │                   PRIVACY LAYER (Optional)                 │ │
│  │  • zkKYC (Polygon ID)                                     │ │
│  │  • Privacy payments (RAILGUN integration)                 │ │
│  │  • SBT attestations (on-chain credentials)                │ │
│  └───────────────────────────────────────────────────────────┘ │
│                              │                                  │
│  ┌───────────────────────────▼───────────────────────────────┐ │
│  │                   BLOCKCHAIN LAYER                         │ │
│  │  • Multi-chain support (EVM, Solana, Bitcoin)            │ │
│  │  • Hardware wallet integration (Ledger, Trezor)          │ │
│  │  • Smart contract interaction                             │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Summary: Key Takeaways

### 1. Key Management

**Best Practice**: Shamir's Secret Sharing + TEE (Privy model)
- Device shard + Auth shard = Active signing
- Recovery shard = Account recovery
- Keys never exist as whole outside TEE

**Alternative**: MPC Threshold Signatures (Fireblocks model)
- No key reconstruction ever
- Better for enterprise multi-sig
- More complex implementation

### 2. Authentication

**Best Practice**: Passkeys (FIDO2) + Biometric fallback
- Regulatory compliant (NIST AAL2, eIDAS 2.0)
- Phishing-resistant
- Device-bound security

### 3. Privacy

**Best Practice**: Layer privacy features progressively
- v2.2.0: Standard encryption, secure communication
- v2.3.0: zkKYC for privacy-preserving compliance
- v2.4.0: RAILGUN integration for private payments

### 4. Compliance

**Best Practice**: Zero-knowledge proofs for selective disclosure
- Prove "age > 18" without revealing DOB
- Prove "country in EU" without exact location
- 97% reduction in exposed PII

### 5. On-Chain Identity

**Best Practice**: Soulbound tokens for verifiable credentials
- KYC tier attestations
- Accredited investor status
- Governance rights

---

## References

### Industry Leaders
- [Privy](https://www.privy.io/) - Embedded wallet infrastructure
- [Turnkey](https://www.turnkey.com/) - Non-custodial key management
- [RAILGUN](https://www.railgun.org/) - Privacy-preserving DeFi
- [Galactica Network](https://docs.galactica.com/) - zkKYC implementation

### Standards
- [FIDO Alliance - Passkeys](https://fidoalliance.org/passkeys/)
- [NIST - Threshold Cryptography](https://csrc.nist.gov/projects/threshold-cryptography)
- [W3C - Verifiable Credentials](https://www.w3.org/TR/vc-data-model/)
- [EIP-5114 - Soulbound Tokens](https://eips.ethereum.org/EIPS/eip-5114)

### Research Papers
- [zkKYC: A solution concept for KYC without knowing your customer](https://eprint.iacr.org/2021/907.pdf)
- [a16z - Privacy-Protecting Regulatory Solutions Using ZK Proofs](https://a16zcrypto.com/posts/article/privacy-protecting-regulatory-solutions-using-zero-knowledge-proofs-full-paper/)

---

*Document created: February 1, 2026*  
*For: FinAegis v2.2.0+ Mobile Wallet Architecture*  
*Next review: Post v2.2.0 release*
