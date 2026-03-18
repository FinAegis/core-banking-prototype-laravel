<?php

declare(strict_types=1);

use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\Services\OnChainVerifierService;
use App\Domain\Privacy\ValueObjects\ZkProof;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function makeTestProof(): ZkProof
{
    $proofJson = json_encode([
        'pi_a' => ['12345', '67890'],
        'pi_b' => [['111', '222'], ['333', '444']],
        'pi_c' => ['555', '666'],
    ], JSON_THROW_ON_ERROR);

    return new ZkProof(
        type: ProofType::AGE_VERIFICATION,
        proof: base64_encode($proofJson),
        publicInputs: ['1000', '500'],
        verifierAddress: '0x1234567890abcdef1234567890abcdef12345678',
        createdAt: new DateTimeImmutable(),
        expiresAt: new DateTimeImmutable('+90 days'),
        metadata: ['provider' => 'snarkjs', 'circuit' => 'balance_v1'],
    );
}

it('returns fallback result when no RPC configured', function (): void {
    config()->set('privacy.zk.rpc_url', '');
    config()->set('privacy.zk.verifier_addresses', []);

    $service = new OnChainVerifierService();
    $result = $service->verifyOnChain(makeTestProof());

    expect($result)->toHaveKeys(['verified', 'tx_hash', 'gas_used', 'chain']);
    expect($result['chain'])->toBe('local');
    expect($result['tx_hash'])->toBeNull();
});

it('verifies proof on-chain via RPC', function (): void {
    config()->set('privacy.zk.rpc_url', 'https://rpc.example.com');
    config()->set('privacy.zk.verifier_addresses', [
        'age_verification' => '0xaabbccdd00000000000000000000000000000001',
    ]);

    // Successful verification returns true (0x1 padded to 32 bytes)
    Http::fake([
        'rpc.example.com' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x0000000000000000000000000000000000000000000000000000000000000001'])
            ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x38d7e']), // gas estimate
    ]);

    $service = new OnChainVerifierService();
    $result = $service->verifyOnChain(makeTestProof());

    expect($result['verified'])->toBeTrue();
    expect($result['chain'])->toBe('unknown');
});

it('returns false when RPC returns zero', function (): void {
    config()->set('privacy.zk.rpc_url', 'https://mainnet.example.com');
    config()->set('privacy.zk.verifier_addresses', ['age_verification' => '0x1111111111111111111111111111111111111111']);

    Http::fake([
        'mainnet.example.com' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x0000000000000000000000000000000000000000000000000000000000000000'])
            ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x38d7e']),
    ]);

    $service = new OnChainVerifierService();
    $result = $service->verifyOnChain(makeTestProof());

    expect($result['verified'])->toBeFalse();
    expect($result['chain'])->toBe('ethereum');
});

it('returns false on RPC failure', function (): void {
    config()->set('privacy.zk.rpc_url', 'https://polygon.example.com');
    config()->set('privacy.zk.verifier_addresses', ['age_verification' => '0x1111111111111111111111111111111111111111']);

    Http::fake([
        'polygon.example.com' => Http::response(null, 500),
    ]);

    $service = new OnChainVerifierService();
    $result = $service->verifyOnChain(makeTestProof());

    expect($result['verified'])->toBeFalse();
    expect($result['chain'])->toBe('polygon');
});

it('estimates verification gas', function (): void {
    config()->set('privacy.zk.rpc_url', '');

    $service = new OnChainVerifierService();
    $gas = $service->estimateVerificationGas(makeTestProof());

    // Fallback is 230k gas for Groth16
    expect($gas)->toBe(230000);
});

it('detects verifier deployment status', function (): void {
    config()->set('privacy.zk.verifier_addresses', [
        'age_verification' => '0xaabbccdd00000000000000000000000000000001',
        'kyc_tier'         => '0x0000000000000000000000000000000000000000',
    ]);

    $service = new OnChainVerifierService();

    expect($service->isVerifierDeployed(ProofType::AGE_VERIFICATION))->toBeTrue();
    expect($service->isVerifierDeployed(ProofType::KYC_TIER))->toBeFalse();
});

it('gets deployment status for all proof types', function (): void {
    config()->set('privacy.zk.verifier_addresses', [
        'age_verification' => '0xaabbccdd00000000000000000000000000000001',
    ]);

    $service = new OnChainVerifierService();
    $status = $service->getDeploymentStatus();

    expect($status)->toBeArray();
    expect($status['age_verification']['deployed'])->toBeTrue();
    expect($status['age_verification']['address'])->toBe('0xaabbccdd00000000000000000000000000000001');
});

it('detects ethereum chain from RPC URL', function (): void {
    config()->set('privacy.zk.rpc_url', 'https://mainnet.infura.io');
    config()->set('privacy.zk.verifier_addresses', ['age_verification' => '0x1111111111111111111111111111111111111111']);
    Http::fake(['*' => Http::response(null, 500)]);
    $result = (new OnChainVerifierService())->verifyOnChain(makeTestProof());
    expect($result['chain'])->toBe('ethereum');
});

it('detects polygon chain from RPC URL', function (): void {
    config()->set('privacy.zk.rpc_url', 'https://polygon-rpc.com');
    config()->set('privacy.zk.verifier_addresses', ['age_verification' => '0x1111111111111111111111111111111111111111']);
    Http::fake(['*' => Http::response(null, 500)]);
    $result = (new OnChainVerifierService())->verifyOnChain(makeTestProof());
    expect($result['chain'])->toBe('polygon');
});
