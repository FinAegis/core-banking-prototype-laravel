<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Contracts\MerkleTreeServiceInterface;
use App\Domain\Privacy\Exceptions\CommitmentNotFoundException;
use App\Domain\Privacy\ValueObjects\MerklePath;
use App\Domain\Privacy\ValueObjects\MerkleRoot;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * RAILGUN-backed Merkle tree service.
 *
 * Delegates Merkle root and proof queries to the Node.js RAILGUN bridge,
 * which reads directly from the on-chain RAILGUN smart contracts.
 *
 * Supported networks: ethereum, polygon, arbitrum, bsc (NOT base).
 */
class RailgunMerkleTreeService implements MerkleTreeServiceInterface
{
    /** @var array<string> */
    private readonly array $supportedNetworks;

    public function __construct(
        private readonly RailgunBridgeClient $bridge,
    ) {
        /** @var array<string> $networks */
        $networks = config('privacy.railgun.networks', ['ethereum', 'polygon', 'arbitrum', 'bsc']);
        $this->supportedNetworks = $networks;
    }

    public function getMerkleRoot(string $network): MerkleRoot
    {
        $this->assertNetworkSupported($network);

        $data = $this->bridge->getMerkleRoot($network);

        return new MerkleRoot(
            root: $data['root'],
            network: $network,
            leafCount: (int) ($data['leaf_count'] ?? 0),
            treeDepth: (int) ($data['tree_depth'] ?? 32),
            blockNumber: (int) ($data['block_number'] ?? 0),
            syncedAt: new DateTimeImmutable($data['synced_at'] ?? 'now'),
        );
    }

    public function getMerklePath(string $commitment, string $network): MerklePath
    {
        $this->assertNetworkSupported($network);

        try {
            $data = $this->bridge->getMerkleProof($commitment, $network);
        } catch (RuntimeException $e) {
            throw new CommitmentNotFoundException(
                commitment: $commitment,
                network: $network,
                message: 'Commitment not found in RAILGUN Merkle tree: ' . $e->getMessage(),
            );
        }

        // The RAILGUN SDK handles Merkle proofs internally during proof generation.
        // This provides the verification data available from the contract state.
        return new MerklePath(
            commitment: $commitment,
            root: $data['root'] ?? '',
            network: $network,
            leafIndex: (int) ($data['leaf_index'] ?? 0),
            siblings: $data['siblings'] ?? [],
            pathIndices: $data['path_indices'] ?? [],
        );
    }

    public function verifyCommitment(string $commitment, MerklePath $path): bool
    {
        // Verification is handled by the RAILGUN SDK on the bridge side.
        // If we were able to retrieve the proof, the commitment is verified.
        try {
            $data = $this->bridge->getMerkleProof($commitment, $path->network);

            return ($data['verified'] ?? false) === true;
        } catch (RuntimeException $e) {
            Log::warning('RAILGUN commitment verification failed', [
                'commitment' => $commitment,
                'network'    => $path->network,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function supportsNetwork(string $network): bool
    {
        return in_array(strtolower($network), $this->supportedNetworks, true);
    }

    public function getSupportedNetworks(): array
    {
        return $this->supportedNetworks;
    }

    public function syncTree(string $network): MerkleRoot
    {
        $this->assertNetworkSupported($network);

        // Trigger a wallet scan which refreshes the Merkle tree state
        try {
            $this->bridge->scanWallet('system', $network);
        } catch (RuntimeException $e) {
            Log::info('Merkle tree sync scan skipped (no system wallet)', [
                'network' => $network,
                'reason'  => $e->getMessage(),
            ]);
        }

        // Return the current root after sync
        return $this->getMerkleRoot($network);
    }

    public function getTreeDepth(): int
    {
        return (int) config('privacy.merkle.max_tree_depth', 32);
    }

    public function getProviderName(): string
    {
        return 'railgun';
    }

    private function assertNetworkSupported(string $network): void
    {
        if (! $this->supportsNetwork($network)) {
            throw new RuntimeException(
                "Network '{$network}' is not supported by RAILGUN. Supported: " . implode(', ', $this->supportedNetworks),
            );
        }
    }
}
