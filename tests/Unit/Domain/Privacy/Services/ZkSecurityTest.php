<?php

declare(strict_types=1);

use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\Exceptions\CircuitNotFoundException;
use App\Domain\Privacy\Services\SnarkjsProverService;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

/**
 * Security-focused tests for ZK proof generation:
 *   Finding #4  — temp files cleaned up even on failure
 *   Finding #6  — concurrency semaphore limits parallel proofs
 *   Finding #13 — circuit file integrity verified against SHA-256 manifest
 */
beforeEach(function (): void {
    config(['privacy.zk.circuit_mapping' => [
        'age_verification' => 'age_check',
    ]]);
    config(['privacy.zk.snarkjs_binary' => '/nonexistent/snarkjs']);
    config(['privacy.zk.snarkjs_timeout_seconds' => 10]);
    config(['privacy.zk.circuit_directory' => sys_get_temp_dir() . '/zk_security_test_circuits']);
    config(['privacy.zk.max_concurrent_proofs' => 2]);
    config(['cache.default' => 'array']);
});

// ---------------------------------------------------------------------------
// Finding #4: Temp files are cleaned up on failure
// ---------------------------------------------------------------------------

describe('Finding #4 — temp file cleanup', function (): void {
    afterEach(function (): void {
        $dir = sys_get_temp_dir() . '/zk_security_test_circuits';
        @unlink($dir . '/age_check.zkey');
        @unlink($dir . '/age_check.wasm');
        @rmdir($dir);
    });

    it('cleans up temp files even when the snarkjs process fails', function (): void {
        $dir = sys_get_temp_dir() . '/zk_security_test_circuits';
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/age_check.zkey', 'fake_zkey');
        file_put_contents($dir . '/age_check.wasm', 'fake_wasm');

        // Capture temp dir contents before
        $tmpDir = sys_get_temp_dir();
        $before = glob($tmpDir . '/zk_input_*') ?: [];

        $service = new SnarkjsProverService();

        try {
            $service->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01'],
                ['minimum_age' => 18],
            );
        } catch (Throwable) {
            // Expected — binary does not exist
        }

        // No new zk_input_ temp files should remain
        $after = glob($tmpDir . '/zk_input_*') ?: [];
        $newFiles = array_diff($after, $before);

        expect($newFiles)->toBeEmpty('Private input temp files must be deleted after failure');
    });

    it('cleans up proof and public output temp files after failure', function (): void {
        $dir = sys_get_temp_dir() . '/zk_security_test_circuits';
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/age_check.zkey', 'fake_zkey');
        file_put_contents($dir . '/age_check.wasm', 'fake_wasm');

        $tmpDir = sys_get_temp_dir();
        $beforeProof = glob($tmpDir . '/zk_proof_*') ?: [];
        $beforePublic = glob($tmpDir . '/zk_public_*') ?: [];

        $service = new SnarkjsProverService();

        try {
            $service->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01'],
                ['minimum_age' => 18],
            );
        } catch (Throwable) {
            // Expected
        }

        $afterProof = glob($tmpDir . '/zk_proof_*') ?: [];
        $afterPublic = glob($tmpDir . '/zk_public_*') ?: [];

        expect(array_diff($afterProof, $beforeProof))->toBeEmpty('Proof temp files must be deleted after failure');
        expect(array_diff($afterPublic, $beforePublic))->toBeEmpty('Public temp files must be deleted after failure');
    });
});

// ---------------------------------------------------------------------------
// Finding #6: Concurrency semaphore
// ---------------------------------------------------------------------------

describe('Finding #6 — concurrency semaphore', function (): void {
    afterEach(function (): void {
        $dir = sys_get_temp_dir() . '/zk_security_test_circuits';
        @unlink($dir . '/age_check.zkey');
        @unlink($dir . '/age_check.wasm');
        @rmdir($dir);
    });

    it('rejects proof requests when all slots are occupied', function (): void {
        $dir = sys_get_temp_dir() . '/zk_security_test_circuits';
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/age_check.zkey', 'fake_zkey');
        file_put_contents($dir . '/age_check.wasm', 'fake_wasm');

        // Pre-acquire both slots (max_concurrent_proofs = 2)
        $slot0 = Cache::lock('zk_proof_slot:0', 120);
        $slot1 = Cache::lock('zk_proof_slot:1', 120);
        $slot0->get();
        $slot1->get();

        $service = new SnarkjsProverService();

        expect(fn () => $service->generateProof(
            ProofType::AGE_VERIFICATION,
            ['date_of_birth' => '1990-01-01'],
            ['minimum_age' => 18],
        ))->toThrow(RuntimeException::class, 'ZK proof generation at capacity');

        $slot0->release();
        $slot1->release();
    });

    it('acquires a free slot when one is available', function (): void {
        $dir = sys_get_temp_dir() . '/zk_security_test_circuits';
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/age_check.zkey', 'fake_zkey');
        file_put_contents($dir . '/age_check.wasm', 'fake_wasm');

        // Pre-acquire only slot 0, leaving slot 1 free
        $slot0 = Cache::lock('zk_proof_slot:0', 120);
        $slot0->get();

        $service = new SnarkjsProverService();

        // Should not throw RuntimeException('at capacity') — will throw SnarkjsException instead
        // because the binary doesn't exist, but a slot was obtained.
        try {
            $service->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01'],
                ['minimum_age' => 18],
            );
        } catch (App\Domain\Privacy\Exceptions\SnarkjsException $e) {
            expect($e->getMessage())->not->toContain('at capacity');
        } catch (RuntimeException $e) {
            // Must not be the capacity error
            expect($e->getMessage())->not->toContain('at capacity');
        }

        $slot0->release();
    });

    it('releases the semaphore slot after a failed proof attempt', function (): void {
        $dir = sys_get_temp_dir() . '/zk_security_test_circuits';
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/age_check.zkey', 'fake_zkey');
        file_put_contents($dir . '/age_check.wasm', 'fake_wasm');

        config(['privacy.zk.max_concurrent_proofs' => 1]);
        $service = new SnarkjsProverService();

        // First call fails but must release its slot
        try {
            $service->generateProof(ProofType::AGE_VERIFICATION, ['dob' => '1990'], ['min' => 18]);
        } catch (Throwable) {
        }

        // Second call must succeed in acquiring the slot (won't throw "at capacity")
        $threwCapacity = false;

        try {
            $service->generateProof(ProofType::AGE_VERIFICATION, ['dob' => '1990'], ['min' => 18]);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'at capacity')) {
                $threwCapacity = true;
            }
        } catch (Throwable) {
            // SnarkjsException or similar — slot was obtained, which is what we care about
        }

        expect($threwCapacity)->toBeFalse('Slot must be released after a failed proof attempt');
    });
});

// ---------------------------------------------------------------------------
// Finding #13: Circuit file integrity verification
// ---------------------------------------------------------------------------

describe('Finding #13 — circuit integrity verification', function (): void {
    afterEach(function (): void {
        $circuitDir = storage_path('app/circuits');
        @unlink($circuitDir . '/circuit_manifest.json');
        @unlink($circuitDir . '/age_check.zkey');
        @unlink($circuitDir . '/age_check.wasm');
        $dir = sys_get_temp_dir() . '/zk_security_test_circuits';
        @unlink($dir . '/age_check.zkey');
        @unlink($dir . '/age_check.wasm');
        @rmdir($dir);
    });

    it('allows proof generation when no manifest exists', function (): void {
        $dir = sys_get_temp_dir() . '/zk_security_test_circuits';
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/age_check.zkey', 'fake_zkey');
        file_put_contents($dir . '/age_check.wasm', 'fake_wasm');

        // Ensure no manifest exists in the storage/app/circuits directory
        $manifestPath = storage_path('app/circuits/circuit_manifest.json');
        $manifestExisted = file_exists($manifestPath);
        $backupPath = null;

        if ($manifestExisted) {
            $backupPath = $manifestPath . '.bak_test';
            rename($manifestPath, $backupPath);
        }

        $service = new SnarkjsProverService();

        // Should NOT throw integrity exception — only snarkjs binary failure
        try {
            $service->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01'],
                ['minimum_age' => 18],
            );
        } catch (App\Domain\Privacy\Exceptions\SnarkjsException | CircuitNotFoundException $e) {
            // Expected — binary missing, not integrity
            expect($e->getMessage())->not->toContain('integrity');
        } catch (RuntimeException $e) {
            // Must not be an integrity failure
            expect($e->getMessage())->not->toContain('integrity');
        }

        if ($backupPath !== null) {
            rename($backupPath, $manifestPath);
        }
    });

    it('blocks proof generation when circuit hash does not match manifest', function (): void {
        $dir = sys_get_temp_dir() . '/zk_security_test_circuits';
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/age_check.zkey', 'fake_zkey');
        file_put_contents($dir . '/age_check.wasm', 'fake_wasm');

        $circuitDir = storage_path('app/circuits');
        @mkdir($circuitDir, 0755, true);
        $manifestPath = $circuitDir . '/circuit_manifest.json';

        // Write a manifest with a deliberately wrong hash
        $manifest = [
            'age_check' => [
                'age_check.wasm' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
                'age_check.zkey' => 'cafecafecafecafecafecafecafecafecafecafecafecafecafecafecafecafe',
            ],
        ];
        file_put_contents($manifestPath, json_encode($manifest));

        // Point circuit directory at real storage path so integrity check finds the files
        config(['privacy.zk.circuit_directory' => $circuitDir]);
        file_put_contents($circuitDir . '/age_check.zkey', 'real_zkey_content');
        file_put_contents($circuitDir . '/age_check.wasm', 'real_wasm_content');

        $service = new SnarkjsProverService();

        expect(fn () => $service->generateProof(
            ProofType::AGE_VERIFICATION,
            ['date_of_birth' => '1990-01-01'],
            ['minimum_age' => 18],
        ))->toThrow(RuntimeException::class, 'integrity');
    });

    it('passes integrity check when all circuit hashes match the manifest', function (): void {
        $circuitDir = storage_path('app/circuits');
        @mkdir($circuitDir, 0755, true);

        $wasmContent = 'valid_wasm_content_for_test';
        $zkeyContent = 'valid_zkey_content_for_test';
        file_put_contents($circuitDir . '/age_check.wasm', $wasmContent);
        file_put_contents($circuitDir . '/age_check.zkey', $zkeyContent);

        $manifest = [
            'age_check' => [
                'age_check.wasm' => hash('sha256', $wasmContent),
                'age_check.zkey' => hash('sha256', $zkeyContent),
            ],
        ];
        $manifestPath = $circuitDir . '/circuit_manifest.json';
        file_put_contents($manifestPath, json_encode($manifest));

        config(['privacy.zk.circuit_directory' => $circuitDir]);
        $service = new SnarkjsProverService();

        // Should NOT throw integrity exception — only snarkjs binary failure
        try {
            $service->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01'],
                ['minimum_age' => 18],
            );
        } catch (App\Domain\Privacy\Exceptions\SnarkjsException $e) {
            expect($e->getMessage())->not->toContain('integrity');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->not->toContain('integrity');
        }
    });
});
