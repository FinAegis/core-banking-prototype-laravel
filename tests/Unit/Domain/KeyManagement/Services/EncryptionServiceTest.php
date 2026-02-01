<?php

declare(strict_types=1);

use App\Domain\KeyManagement\Services\EncryptionService;

uses(Tests\TestCase::class);

describe('EncryptionService', function () {
    beforeEach(function () {
        $this->service = new EncryptionService();
    });

    describe('user encryption', function () {
        it('encrypts and decrypts data for a user', function () {
            $originalData = 'sensitive user data';
            $userId = 'user-123';

            $encrypted = $this->service->encryptForUser($originalData, $userId);
            $decrypted = $this->service->decryptForUser($encrypted, $userId);

            expect($decrypted)->toBe($originalData)
                ->and($encrypted)->not->toBe($originalData);
        });

        it('produces different ciphertext for same data due to random IV', function () {
            $data = 'test data';
            $userId = 'user-123';

            $encrypted1 = $this->service->encryptForUser($data, $userId);
            $encrypted2 = $this->service->encryptForUser($data, $userId);

            expect($encrypted1)->not->toBe($encrypted2);
        });

        it('uses different keys for different users', function () {
            $data = 'shared data';

            $encrypted1 = $this->service->encryptForUser($data, 'user-1');
            $encrypted2 = $this->service->encryptForUser($data, 'user-2');

            // User 1 cannot decrypt user 2's data
            expect(fn () => $this->service->decryptForUser($encrypted1, 'user-2'))
                ->toThrow(RuntimeException::class);
        });
    });

    describe('password encryption', function () {
        it('encrypts and decrypts with password', function () {
            $data = 'recovery phrase data';
            $password = 'secure-password-123';

            $encrypted = $this->service->encryptWithPassword($data, $password);
            $decrypted = $this->service->decryptWithPassword($encrypted, $password);

            expect($decrypted)->toBe($data);
        });

        it('fails with wrong password', function () {
            $data = 'secret data';
            $encrypted = $this->service->encryptWithPassword($data, 'correct-password');

            expect(fn () => $this->service->decryptWithPassword($encrypted, 'wrong-password'))
                ->toThrow(RuntimeException::class);
        });

        it('handles complex passwords', function () {
            $data = 'test';
            $password = 'p@$$w0rd!#$%^&*()_+-=[]{}|;:,.<>?/~`';

            $encrypted = $this->service->encryptWithPassword($data, $password);
            $decrypted = $this->service->decryptWithPassword($encrypted, $password);

            expect($decrypted)->toBe($data);
        });
    });

    describe('multi-party encryption', function () {
        it('encrypts with multiple key holders', function () {
            $data = 'audit vault data';
            $keyHolders = ['holder1', 'holder2', 'holder3'];

            $encrypted = $this->service->encryptWithMultiParty($data, $keyHolders);

            expect($encrypted)->not->toBe($data)
                ->and($encrypted)->toBeString();
        });
    });

    describe('error handling', function () {
        it('throws on invalid encrypted data format', function () {
            expect(fn () => $this->service->decryptForUser('invalid', 'user'))
                ->toThrow(RuntimeException::class);
        });

        it('throws on tampered ciphertext', function () {
            $encrypted = $this->service->encryptForUser('data', 'user');

            // Tamper with the ciphertext
            $decoded = base64_decode($encrypted);
            $tampered = $decoded;
            $tampered[20] = chr(ord($tampered[20]) ^ 0xFF);
            $tamperedEncrypted = base64_encode($tampered);

            expect(fn () => $this->service->decryptForUser($tamperedEncrypted, 'user'))
                ->toThrow(RuntimeException::class);
        });
    });

    describe('data integrity', function () {
        it('handles binary data', function () {
            $binaryData = random_bytes(256);
            $userId = 'user-binary';

            $encrypted = $this->service->encryptForUser($binaryData, $userId);
            $decrypted = $this->service->decryptForUser($encrypted, $userId);

            expect($decrypted)->toBe($binaryData);
        });

        it('handles large data', function () {
            $largeData = str_repeat('x', 100000); // 100KB
            $userId = 'user-large';

            $encrypted = $this->service->encryptForUser($largeData, $userId);
            $decrypted = $this->service->decryptForUser($encrypted, $userId);

            expect($decrypted)->toBe($largeData);
        });

        it('handles empty string', function () {
            $encrypted = $this->service->encryptForUser('', 'user');
            $decrypted = $this->service->decryptForUser($encrypted, 'user');

            expect($decrypted)->toBe('');
        });

        it('handles unicode data', function () {
            $unicodeData = 'Hello 世界 🌍 مرحبا';

            $encrypted = $this->service->encryptForUser($unicodeData, 'user');
            $decrypted = $this->service->decryptForUser($encrypted, 'user');

            expect($decrypted)->toBe($unicodeData);
        });
    });
});
