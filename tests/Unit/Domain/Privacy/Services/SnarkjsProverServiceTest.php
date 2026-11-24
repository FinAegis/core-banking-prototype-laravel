<?php

declare(strict_types=1);

use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\Exceptions\CircuitNotFoundException;
use App\Domain\Privacy\Exceptions\SnarkjsException;
use App\Domain\Privacy\Services\SnarkjsProverService;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    // Configure circuit mapping
    config(['privacy.zk.circuit_mapping' => [
        'age_verification'    => 'age_check',
        'residency'           => 'residency_check',
        'kyc_tier'            => 'kyc_tier_check',
        'accredited_investor' => 'accredited_check',
        'sanctions_clear'     => 'sanctions_check',
        'income_range'        => 'income_range_check',
    ]]);
    config(['privacy.zk.snarkjs_binary' => 'snarkjs']);
    config(['privacy.zk.snarkjs_timeout_seconds' => 30]);
    config(['privacy.zk.circuit_directory' => sys_get_temp_dir() . '/test_circuits']);
});

describe('SnarkjsProverService', function (): void {
    describe('getProviderName', function (): void {
        it('returns snarkjs', function (): void {
            $service = new SnarkjsProverService();
            expect($service->getProviderName())->toBe('snarkjs');
        });
    });

    describe('supportsProofType', function (): void {
        it('supports mapped proof types', function (): void {
            $service = new SnarkjsProverService();
            expect($service->supportsProofType(ProofType::AGE_VERIFICATION))->toBeTrue();
            expect($service->supportsProofType(ProofType::KYC_TIER))->toBeTrue();
            expect($service->supportsProofType(ProofType::SANCTIONS_CLEAR))->toBeTrue();
        });
    });

    describe('getVerifierAddress', function (): void {
        it('returns configured verifier address', function (): void {
            config(['privacy.zk.verifier_addresses.age_verification' => '0xABC123']);
            $service = new SnarkjsProverService();
            expect($service->getVerifierAddress(ProofType::AGE_VERIFICATION))->toBe('0xABC123');
        });

        it('returns zero address as default', function (): void {
            $service = new SnarkjsProverService();
            expect($service->getVerifierAddress(ProofType::CUSTOM))->toStartWith('0x');
        });
    });

    describe('generateProof', function (): void {
        it('throws CircuitNotFoundException for unmapped type', function (): void {
            config(['privacy.zk.circuit_mapping' => []]);
            $service = new SnarkjsProverService();

            $service->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01'],
                ['minimum_age' => 18],
            );
        })->throws(CircuitNotFoundException::class);

        it('throws CircuitNotFoundException when zkey file missing', function (): void {
            config(['privacy.zk.circuit_directory' => '/nonexistent/path']);
            $service = new SnarkjsProverService();

            $service->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01'],
                ['minimum_age' => 18],
            );
        })->throws(CircuitNotFoundException::class);

        it('throws SnarkjsException when snarkjs process fails', function (): void {
            // Create fake zkey and wasm files so validation passes
            $dir = sys_get_temp_dir() . '/test_circuits';
            @mkdir($dir, 0755, true);
            file_put_contents($dir . '/age_check.zkey', 'fake_zkey');
            file_put_contents($dir . '/age_check.wasm', 'fake_wasm');

            config(['privacy.zk.snarkjs_binary' => '/nonexistent/binary']);
            $service = new SnarkjsProverService();

            try {
                $service->generateProof(
                    ProofType::AGE_VERIFICATION,
                    ['date_of_birth' => '1990-01-01'],
                    ['minimum_age' => 18],
                );
                $this->fail('Expected SnarkjsException');
            } catch (SnarkjsException $e) {
                expect($e->getMessage())->toContain('age_check');
            } finally {
                @unlink($dir . '/age_check.zkey');
                @unlink($dir . '/age_check.wasm');
            }
        });
    });

    describe('verifyProof', function (): void {
        it('returns false for expired proof', function (): void {
            $service = new SnarkjsProverService();

            $proof = new App\Domain\Privacy\ValueObjects\ZkProof(
                type: ProofType::AGE_VERIFICATION,
                proof: base64_encode('{}'),
                publicInputs: ['minimum_age' => 18],
                verifierAddress: '0x0000000000000000000000000000000000000000',
                createdAt: new DateTimeImmutable('-100 days'),
                expiresAt: new DateTimeImmutable('-1 day'),
            );

            expect($service->verifyProof($proof))->toBeFalse();
        });

        it('returns false for invalid base64 proof', function (): void {
            $service = new SnarkjsProverService();

            $proof = new App\Domain\Privacy\ValueObjects\ZkProof(
                type: ProofType::AGE_VERIFICATION,
                proof: '!!!invalid-base64!!!',
                publicInputs: ['minimum_age' => 18],
                verifierAddress: '0x0000000000000000000000000000000000000000',
                createdAt: new DateTimeImmutable(),
                expiresAt: new DateTimeImmutable('+90 days'),
            );

            expect($service->verifyProof($proof))->toBeFalse();
        });

        it('returns false when verification key is missing', function (): void {
            config(['privacy.zk.circuit_directory' => '/nonexistent/path']);
            $service = new SnarkjsProverService();

            $proof = new App\Domain\Privacy\ValueObjects\ZkProof(
                type: ProofType::AGE_VERIFICATION,
                proof: base64_encode('{"pi_a": [], "pi_b": [], "pi_c": []}'),
                publicInputs: ['minimum_age' => 18],
                verifierAddress: '0x0000000000000000000000000000000000000000',
                createdAt: new DateTimeImmutable(),
                expiresAt: new DateTimeImmutable('+90 days'),
            );

            expect($service->verifyProof($proof))->toBeFalse();
        });
    });
});

describe('SnarkjsException', function (): void {
    it('creates timeout exception', function (): void {
        $e = SnarkjsException::processTimeout('age_check', 120);
        expect($e->getMessage())->toContain('timed out');
        expect($e->getMessage())->toContain('120');
    });

    it('creates process failed exception', function (): void {
        $e = SnarkjsException::processFailed('age_check', 'segfault');
        expect($e->getMessage())->toContain('failed');
        expect($e->getMessage())->toContain('segfault');
    });

    it('creates invalid output exception', function (): void {
        $e = SnarkjsException::invalidOutput('age_check', 'empty');
        expect($e->getMessage())->toContain('invalid output');
    });
});

describe('CircuitNotFoundException', function (): void {
    it('creates unmapped proof type exception', function (): void {
        $e = CircuitNotFoundException::unmappedProofType('custom');
        expect($e->getMessage())->toContain('custom');
    });

    it('creates zkey not found exception', function (): void {
        $e = CircuitNotFoundException::zkeyNotFound('age_check', '/path/to/age_check.zkey');
        expect($e->getMessage())->toContain('zkey');
    });
});
