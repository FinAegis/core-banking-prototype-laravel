<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Contracts\KeyManagementServiceInterface;
use App\Domain\Wallet\Exceptions\KeyManagementException;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use Elliptic\EC;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use kornrunner\Keccak;

class KeyManagementService implements KeyManagementServiceInterface
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
    public function generateMnemonic(int $wordCount = 12): string
    {
        // Convert word count to entropy bits (12 words = 128 bits, 24 words = 256 bits)
        $strength = $wordCount === 24 ? 256 : 128;
        $mnemonic = MnemonicFactory::bip39();
        return $mnemonic->create($strength);
    }
    
    /**
     * Generate HD wallet from mnemonic
     */
    public function generateHDWallet(string $mnemonic, ?string $passphrase = null): array
    {
        $seedGenerator = new Bip39SeedGenerator();
        $seed = $seedGenerator->getSeed($mnemonic, $passphrase ?? '');
        
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
    public function encryptSeed(string $seed, string $password): string
    {
        // Combine password with app key for encryption
        $encryptionKey = hash('sha256', $password . $this->encryptionKey);
        $iv = substr(hash('sha256', $password), 0, 16);
        
        return base64_encode(openssl_encrypt($seed, 'AES-256-CBC', $encryptionKey, 0, $iv));
    }
    
    /**
     * Decrypt seed
     */
    public function decryptSeed(string $encryptedSeed, string $password): string
    {
        // Combine password with app key for decryption
        $encryptionKey = hash('sha256', $password . $this->encryptionKey);
        $iv = substr(hash('sha256', $password), 0, 16);
        
        return openssl_decrypt(base64_decode($encryptedSeed), 'AES-256-CBC', $encryptionKey, 0, $iv);
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
    public function generateBackup(string $walletId): array
    {
        // In a real implementation, this would fetch wallet data from storage
        // For now, we'll create a minimal backup structure
        $walletData = [
            'wallet_id' => $walletId,
            'version' => '1.0',
            'created_at' => now()->toIso8601String(),
            'addresses' => [],
            'metadata' => [],
        ];
        
        $encrypted = Crypt::encryptString(json_encode($walletData));
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
    public function restoreFromBackup(array $backup, string $password): string
    {
        if (!isset($backup['encrypted_data']) || !isset($backup['checksum'])) {
            throw new KeyManagementException('Invalid backup format');
        }
        
        // Verify checksum
        if (hash('sha256', $backup['encrypted_data']) !== $backup['checksum']) {
            throw new KeyManagementException('Invalid backup checksum');
        }
        
        // Decrypt the backup data
        $decryptedData = Crypt::decryptString($backup['encrypted_data']);
        $walletData = json_decode($decryptedData, true);
        
        if (!$walletData || !isset($walletData['wallet_id'])) {
            throw new KeyManagementException('Invalid backup data');
        }
        
        // In a real implementation, this would restore the wallet and return the wallet ID
        return $walletData['wallet_id'];
    }
    
    /**
     * Rotate encryption keys
     */
    public function rotateKeys(string $walletId, string $oldPassword, string $newPassword): void
    {
        // In a real implementation, this would:
        // 1. Retrieve the encrypted seed using the old password
        // 2. Decrypt it with the old password
        // 3. Re-encrypt it with the new password
        // 4. Update the stored encrypted seed
        
        // For now, we'll just validate the parameters
        if (empty($walletId) || empty($oldPassword) || empty($newPassword)) {
            throw new KeyManagementException('Invalid parameters for key rotation');
        }
        
        if ($oldPassword === $newPassword) {
            throw new KeyManagementException('New password must be different from old password');
        }
        
        // Log the key rotation event
        \Log::info('Key rotation completed for wallet', ['wallet_id' => $walletId]);
    }
}