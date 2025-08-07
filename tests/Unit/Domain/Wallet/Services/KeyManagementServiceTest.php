<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Wallet\Services;

use App\Domain\Wallet\Contracts\KeyManagementServiceInterface;
use App\Domain\Wallet\Exceptions\KeyManagementException;
use App\Domain\Wallet\Services\KeyManagementService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KeyManagementServiceTest extends TestCase
{
    private KeyManagementService $keyManagementService;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $this->keyManagementService = new KeyManagementService();
    }

    #[Test]
    public function test_service_implements_interface()
    {
        $this->assertInstanceOf(KeyManagementServiceInterface::class, $this->keyManagementService);
    }

    #[Test]
    public function test_generate_mnemonic_with_12_words()
    {
        $mnemonic = $this->keyManagementService->generateMnemonicWithWordCount(12);

        $words = explode(' ', $mnemonic);
        $this->assertCount(12, $words);
    }

    #[Test]
    public function test_generate_mnemonic_with_24_words()
    {
        $mnemonic = $this->keyManagementService->generateMnemonicWithWordCount(24);

        $words = explode(' ', $mnemonic);
        $this->assertCount(24, $words);
    }

    #[Test]
    public function test_generate_mnemonic_default_method()
    {
        $mnemonic = $this->keyManagementService->generateMnemonic();

        $words = explode(' ', $mnemonic);
        $this->assertCount(12, $words);
    }

    #[Test]
    public function test_validate_mnemonic_with_valid_12_words()
    {
        $mnemonic = $this->keyManagementService->generateMnemonic(12);

        $isValid = $this->keyManagementService->validateMnemonic($mnemonic);
        $this->assertTrue($isValid);
    }

    #[Test]
    public function test_validate_mnemonic_with_valid_24_words()
    {
        $mnemonic = $this->keyManagementService->generateMnemonic(24);

        $isValid = $this->keyManagementService->validateMnemonic($mnemonic);
        $this->assertTrue($isValid);
    }

    #[Test]
    public function test_validate_mnemonic_with_invalid_word_count()
    {
        $invalidMnemonic = 'word1 word2 word3'; // Only 3 words

        $isValid = $this->keyManagementService->validateMnemonic($invalidMnemonic);
        $this->assertFalse($isValid);
    }

    #[Test]
    public function test_generate_hd_wallet_from_mnemonic()
    {
        $mnemonic = $this->keyManagementService->generateMnemonic();

        $wallet = $this->keyManagementService->generateHDWallet($mnemonic);

        $this->assertArrayHasKey('master_public_key', $wallet);
        $this->assertArrayHasKey('master_chain_code', $wallet);
        $this->assertArrayHasKey('encrypted_seed', $wallet);
        $this->assertIsString($wallet['master_public_key']);
        $this->assertIsString($wallet['master_chain_code']);
        $this->assertIsString($wallet['encrypted_seed']);
    }

    #[Test]
    public function test_generate_hd_wallet_with_passphrase()
    {
        $mnemonic = $this->keyManagementService->generateMnemonic();
        $passphrase = 'test-passphrase';

        $walletWithPassphrase = $this->keyManagementService->generateHDWallet($mnemonic, $passphrase);
        $walletWithoutPassphrase = $this->keyManagementService->generateHDWallet($mnemonic);

        // Different passphrases should generate different seeds
        $this->assertNotEquals(
            $walletWithPassphrase['encrypted_seed'],
            $walletWithoutPassphrase['encrypted_seed']
        );
    }

    #[Test]
    public function test_derive_key_pair_for_ethereum_chain()
    {
        $mnemonic = $this->keyManagementService->generateMnemonic();
        $wallet = $this->keyManagementService->generateHDWallet($mnemonic);

        $keyPair = $this->keyManagementService->deriveKeyPairForChain(
            $wallet['encrypted_seed'],
            'ethereum',
            0
        );

        $this->assertArrayHasKey('private_key', $keyPair);
        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('address', $keyPair);
        $this->assertArrayHasKey('derivation_path', $keyPair);
        $this->assertStringStartsWith('0x', $keyPair['address']);
        $this->assertEquals("m/44'/60'/0'/0/0", $keyPair['derivation_path']);
    }

    #[Test]
    public function test_derive_key_pair_for_bitcoin_chain()
    {
        $mnemonic = $this->keyManagementService->generateMnemonic();
        $wallet = $this->keyManagementService->generateHDWallet($mnemonic);

        $keyPair = $this->keyManagementService->deriveKeyPairForChain(
            $wallet['encrypted_seed'],
            'bitcoin',
            0
        );

        $this->assertArrayHasKey('private_key', $keyPair);
        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('address', $keyPair);
        $this->assertArrayHasKey('derivation_path', $keyPair);
        $this->assertStringStartsWith('1', $keyPair['address']); // Bitcoin addresses start with 1
        $this->assertEquals("m/44'/0'/0'/0/0", $keyPair['derivation_path']);
    }

    #[Test]
    public function test_encrypt_and_decrypt_seed()
    {
        $originalSeed = 'test-seed-data-' . uniqid();
        $password = 'test-password';

        $encryptedSeed = $this->keyManagementService->encryptSeed($originalSeed, $password);
        $this->assertNotEquals($originalSeed, $encryptedSeed);

        $decryptedSeed = $this->keyManagementService->decryptSeed($encryptedSeed, $password);
        $this->assertEquals($originalSeed, $decryptedSeed);
    }

    #[Test]
    public function test_encrypt_and_decrypt_private_key()
    {
        $privateKey = bin2hex(random_bytes(32));
        $userId = 'user-123';

        $encryptedKey = $this->keyManagementService->encryptPrivateKey($privateKey, $userId);
        $this->assertNotEquals($privateKey, $encryptedKey);

        $decryptedKey = $this->keyManagementService->decryptPrivateKey($encryptedKey, $userId);
        $this->assertEquals($privateKey, $decryptedKey);
    }

    #[Test]
    public function test_encrypt_private_key_without_user_id()
    {
        $privateKey = bin2hex(random_bytes(32));

        // Mock Crypt facade
        Crypt::shouldReceive('encryptString')
            ->once()
            ->with($privateKey)
            ->andReturn('encrypted-data');

        $encryptedKey = $this->keyManagementService->encryptPrivateKey($privateKey);
        $this->assertEquals('encrypted-data', $encryptedKey);
    }

    #[Test]
    public function test_generate_backup()
    {
        $walletId = 'wallet-123';
        $data = ['key' => 'value'];

        $backup = $this->keyManagementService->generateBackup($walletId, $data);

        $this->assertArrayHasKey('backup_id', $backup);
        $this->assertArrayHasKey('encrypted_data', $backup);
        $this->assertArrayHasKey('checksum', $backup);
        $this->assertIsString($backup['backup_id']);
        $this->assertIsString($backup['encrypted_data']);
        $this->assertIsString($backup['checksum']);
    }

    #[Test]
    public function test_restore_from_backup()
    {
        $walletId = 'wallet-456';
        $backup = $this->keyManagementService->generateBackup($walletId);

        $restoredWalletId = $this->keyManagementService->restoreFromBackup($backup);

        $this->assertEquals($walletId, $restoredWalletId);
    }

    #[Test]
    public function test_restore_from_backup_with_invalid_checksum()
    {
        $backup = [
            'encrypted_data' => 'invalid-data',
            'checksum'       => 'invalid-checksum',
        ];

        $this->expectException(KeyManagementException::class);
        $this->expectExceptionMessage('Invalid backup checksum');

        $this->keyManagementService->restoreFromBackup($backup);
    }

    #[Test]
    public function test_restore_from_backup_with_missing_fields()
    {
        $backup = ['encrypted_data' => 'some-data'];

        $this->expectException(KeyManagementException::class);
        $this->expectExceptionMessage('Invalid backup format');

        $this->keyManagementService->restoreFromBackup($backup);
    }

    #[Test]
    public function test_rotate_keys()
    {
        $walletId = 'wallet-789';
        $oldPassword = 'old-password';
        $newPassword = 'new-password';

        // Should not throw exception
        $this->keyManagementService->rotateKeys($walletId, $oldPassword, $newPassword);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function test_rotate_keys_with_same_password()
    {
        $walletId = 'wallet-789';
        $password = 'same-password';

        $this->expectException(KeyManagementException::class);
        $this->expectExceptionMessage('New password must be different from old password');

        $this->keyManagementService->rotateKeys($walletId, $password, $password);
    }

    #[Test]
    public function test_rotate_keys_with_empty_parameters()
    {
        $this->expectException(KeyManagementException::class);
        $this->expectExceptionMessage('Invalid parameters for key rotation');

        $this->keyManagementService->rotateKeys('', 'password', 'new-password');
    }

    #[Test]
    public function test_generate_key()
    {
        $key = $this->keyManagementService->generateKey();

        $this->assertEquals(64, strlen($key)); // 32 bytes hex encoded = 64 chars
    }

    #[Test]
    public function test_generate_master_key()
    {
        $masterKey = $this->keyManagementService->generateMasterKey();

        $this->assertEquals(64, strlen($masterKey));
    }

    #[Test]
    public function test_sign_and_verify_message()
    {
        $message = 'Test message to sign';
        $privateKey = $this->keyManagementService->generateKey();

        $signature = $this->keyManagementService->signMessage($message, $privateKey);

        // In this implementation, verification uses the same key (HMAC style)
        $isValid = $this->keyManagementService->verifySignature($message, $signature, $privateKey);
        $this->assertTrue($isValid);
    }

    #[Test]
    public function test_verify_signature_with_wrong_key()
    {
        $message = 'Test message';
        $privateKey = $this->keyManagementService->generateKey();
        $wrongKey = $this->keyManagementService->generateKey();

        $signature = $this->keyManagementService->signMessage($message, $privateKey);

        $isValid = $this->keyManagementService->verifySignature($message, $signature, $wrongKey);
        $this->assertFalse($isValid);
    }

    #[Test]
    public function test_derive_child_key()
    {
        $parentKey = $this->keyManagementService->generateKey();

        $childKey1 = $this->keyManagementService->deriveChildKey($parentKey, 0);
        $childKey2 = $this->keyManagementService->deriveChildKey($parentKey, 1);

        $this->assertNotEquals($childKey1, $childKey2);

        // Same index should generate same key
        $childKey1Again = $this->keyManagementService->deriveChildKey($parentKey, 0);
        $this->assertEquals($childKey1, $childKey1Again);
    }

    #[Test]
    public function test_generate_deterministic_key()
    {
        $seed = 'test-seed-' . uniqid();

        $key1 = $this->keyManagementService->generateDeterministicKey($seed);
        $key2 = $this->keyManagementService->generateDeterministicKey($seed);

        $this->assertEquals($key1, $key2); // Same seed should generate same key
        $this->assertEquals(64, strlen($key1)); // SHA256 hex = 64 chars
    }

    #[Test]
    public function test_derive_key_pair_from_mnemonic()
    {
        $mnemonic = $this->keyManagementService->generateMnemonic();
        $path = "m/44'/60'/0'/0/0"; // Ethereum path

        $keyPair = $this->keyManagementService->deriveKeyPair($mnemonic, $path);

        $this->assertArrayHasKey('private_key', $keyPair);
        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('address', $keyPair);
        $this->assertArrayHasKey('derivation_path', $keyPair);
    }

    #[Test]
    public function test_derive_from_mnemonic()
    {
        $mnemonic = $this->keyManagementService->generateMnemonic();

        $wallet = $this->keyManagementService->deriveFromMnemonic($mnemonic);

        $this->assertArrayHasKey('master_public_key', $wallet);
        $this->assertArrayHasKey('master_chain_code', $wallet);
        $this->assertArrayHasKey('encrypted_seed', $wallet);
    }

    #[Test]
    public function test_encrypt_and_decrypt_general_data()
    {
        $originalData = 'sensitive data ' . uniqid();

        // Mock Crypt facade
        Crypt::shouldReceive('encryptString')
            ->once()
            ->with($originalData)
            ->andReturn('encrypted-general-data');

        Crypt::shouldReceive('decryptString')
            ->once()
            ->with('encrypted-general-data')
            ->andReturn($originalData);

        $encrypted = $this->keyManagementService->encrypt($originalData);
        $this->assertEquals('encrypted-general-data', $encrypted);

        $decrypted = $this->keyManagementService->decrypt($encrypted);
        $this->assertEquals($originalData, $decrypted);
    }

    #[Test]
    public function test_sign_transaction_for_ethereum()
    {
        $privateKey = $this->keyManagementService->generateKey();
        $transaction = [
            'to'    => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb81',
            'value' => '1000000000000000000', // 1 ETH in wei
            'gas'   => 21000,
        ];

        $signature = $this->keyManagementService->signTransaction($privateKey, $transaction, 'ethereum');

        $this->assertStringStartsWith('0x', $signature);
    }

    #[Test]
    public function test_sign_transaction_for_bitcoin()
    {
        $privateKey = $this->keyManagementService->generateKey();
        $transaction = [
            'inputs'  => [],
            'outputs' => [],
        ];

        $signature = $this->keyManagementService->signTransaction($privateKey, $transaction, 'bitcoin');

        $this->assertEquals(64, strlen($signature)); // 32 bytes hex
    }

    #[Test]
    public function test_sign_transaction_for_unsupported_chain()
    {
        $privateKey = $this->keyManagementService->generateKey();
        $transaction = [];

        $this->expectException(KeyManagementException::class);
        $this->expectExceptionMessage('Unsupported chain: unsupported');

        $this->keyManagementService->signTransaction($privateKey, $transaction, 'unsupported');
    }
}
