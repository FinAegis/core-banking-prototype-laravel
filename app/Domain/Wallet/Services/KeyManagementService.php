<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Exceptions\KeyManagementException;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use Elliptic\EC;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use kornrunner\Keccak;

class KeyManagementService
{
    protected EC $ec;
    protected string $encryptionKey;
    
    // BIP44 derivation paths
    const DERIVATION_PATHS = [
        'ethereum' => "m/44'/60'/0'/0",
        'bitcoin' => "m/44'/0'/0'/0",
        'polygon' => "m/44'/966'/0'/0",
        'bsc' => "m/44'/60'/0'/0", // Same as Ethereum
    ];
    
    public function __construct()
    {
        $this->ec = new EC('secp256k1');
        $this->encryptionKey = config('app.key');
    }
    
    /**
     * Generate a new mnemonic phrase
     */
    public function generateMnemonic(int $strength = 128): string
    {
        $mnemonic = MnemonicFactory::bip39();
        return $mnemonic->create($strength);
    }
    
    /**
     * Generate HD wallet from mnemonic
     */
    public function generateHDWallet(string $mnemonic, string $passphrase = ''): array
    {
        $seedGenerator = new Bip39SeedGenerator();
        $seed = $seedGenerator->getSeed($mnemonic, $passphrase);
        
        $hdFactory = new HierarchicalKeyFactory();
        $masterKey = $hdFactory->fromEntropy($seed);
        
        return [
            'master_public_key' => $masterKey->getPublicKey()->getHex(),
            'master_chain_code' => bin2hex($masterKey->getChainCode()),
            'encrypted_seed' => $this->encryptSeed($seed->getHex()),
        ];
    }
    
    /**
     * Derive key pair for a specific path
     */
    public function deriveKeyPair(string $encryptedSeed, string $chain, int $index = 0): array
    {
        $seed = $this->decryptSeed($encryptedSeed);
        $hdFactory = new HierarchicalKeyFactory();
        $masterKey = $hdFactory->fromEntropy(hex2bin($seed));
        
        $path = self::DERIVATION_PATHS[$chain] ?? self::DERIVATION_PATHS['ethereum'];
        $derivationPath = $path . '/' . $index;
        
        $derivedKey = $masterKey->derivePath($derivationPath);
        $privateKey = $derivedKey->getPrivateKey();
        
        if (in_array($chain, ['ethereum', 'polygon', 'bsc'])) {
            // For Ethereum-based chains
            $keyPair = $this->ec->keyFromPrivate($privateKey->getHex());
            $publicKey = $keyPair->getPublic('hex');
            
            return [
                'private_key' => $privateKey->getHex(),
                'public_key' => $publicKey,
                'address' => $this->getEthereumAddress($publicKey),
                'derivation_path' => $derivationPath,
            ];
        } else {
            // For Bitcoin
            return [
                'private_key' => $privateKey->getHex(),
                'public_key' => $derivedKey->getPublicKey()->getHex(),
                'address' => $derivedKey->getAddress()->getAddress(),
                'derivation_path' => $derivationPath,
            ];
        }
    }
    
    /**
     * Generate Ethereum address from public key
     */
    protected function getEthereumAddress(string $publicKey): string
    {
        // Remove '04' prefix if present (uncompressed public key)
        if (substr($publicKey, 0, 2) === '04') {
            $publicKey = substr($publicKey, 2);
        }
        
        $hash = Keccak::hash(hex2bin($publicKey), 256);
        return '0x' . substr($hash, -40);
    }
    
    /**
     * Sign transaction with private key
     */
    public function signTransaction(string $privateKey, array $transaction, string $chain): string
    {
        if (in_array($chain, ['ethereum', 'polygon', 'bsc'])) {
            return $this->signEthereumTransaction($privateKey, $transaction);
        } elseif ($chain === 'bitcoin') {
            return $this->signBitcoinTransaction($privateKey, $transaction);
        }
        
        throw new KeyManagementException("Unsupported chain: {$chain}");
    }
    
    /**
     * Sign Ethereum transaction
     */
    protected function signEthereumTransaction(string $privateKey, array $transaction): string
    {
        // Implementation would use web3.php or similar library
        // This is a placeholder
        return '0x' . bin2hex(random_bytes(32));
    }
    
    /**
     * Sign Bitcoin transaction
     */
    protected function signBitcoinTransaction(string $privateKey, array $transaction): string
    {
        // Implementation would use BitWasp Bitcoin library
        // This is a placeholder
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Encrypt seed for storage
     */
    public function encryptSeed(string $seed): string
    {
        return Crypt::encryptString($seed);
    }
    
    /**
     * Decrypt seed
     */
    public function decryptSeed(string $encryptedSeed): string
    {
        return Crypt::decryptString($encryptedSeed);
    }
    
    /**
     * Encrypt private key for temporary storage
     */
    public function encryptPrivateKey(string $privateKey, string $userId): string
    {
        $key = $this->getUserEncryptionKey($userId);
        return openssl_encrypt($privateKey, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
    }
    
    /**
     * Decrypt private key
     */
    public function decryptPrivateKey(string $encryptedKey, string $userId): string
    {
        $key = $this->getUserEncryptionKey($userId);
        return openssl_decrypt($encryptedKey, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
    }
    
    /**
     * Get user-specific encryption key
     */
    protected function getUserEncryptionKey(string $userId): string
    {
        return hash('sha256', $this->encryptionKey . $userId);
    }
    
    /**
     * Store key temporarily in cache (for signing)
     */
    public function storeTemporaryKey(string $userId, string $encryptedKey, int $ttl = 300): string
    {
        $token = bin2hex(random_bytes(32));
        $cacheKey = "wallet_key:{$userId}:{$token}";
        
        Cache::put($cacheKey, $encryptedKey, $ttl);
        
        return $token;
    }
    
    /**
     * Retrieve temporary key from cache
     */
    public function retrieveTemporaryKey(string $userId, string $token): ?string
    {
        $cacheKey = "wallet_key:{$userId}:{$token}";
        $encryptedKey = Cache::pull($cacheKey);
        
        return $encryptedKey;
    }
    
    /**
     * Validate mnemonic phrase
     */
    public function validateMnemonic(string $mnemonic): bool
    {
        try {
            $mnemonicFactory = MnemonicFactory::bip39();
            return $mnemonicFactory->validate($mnemonic);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate wallet backup
     */
    public function generateBackup(string $walletId, array $data): array
    {
        $backup = [
            'wallet_id' => $walletId,
            'version' => '1.0',
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];
        
        $encrypted = Crypt::encryptString(json_encode($backup));
        $checksum = hash('sha256', $encrypted);
        
        return [
            'backup_id' => uniqid('backup_'),
            'encrypted_data' => $encrypted,
            'checksum' => $checksum,
        ];
    }
    
    /**
     * Restore wallet from backup
     */
    public function restoreFromBackup(string $encryptedData, string $checksum): array
    {
        // Verify checksum
        if (hash('sha256', $encryptedData) !== $checksum) {
            throw new KeyManagementException('Invalid backup checksum');
        }
        
        $decrypted = Crypt::decryptString($encryptedData);
        $backup = json_decode($decrypted, true);
        
        if (!$backup || !isset($backup['version']) || !isset($backup['data'])) {
            throw new KeyManagementException('Invalid backup format');
        }
        
        return $backup;
    }
}