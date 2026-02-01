<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Services;

use App\Domain\KeyManagement\Contracts\ShamirServiceInterface;
use App\Domain\KeyManagement\Enums\ShardType;
use App\Domain\KeyManagement\HSM\HsmIntegrationService;
use App\Domain\KeyManagement\ValueObjects\KeyShard;
use App\Domain\KeyManagement\ValueObjects\ReconstructedKey;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class ShamirService implements ShamirServiceInterface
{
    private const DEFAULT_TOTAL_SHARDS = 3;

    private const DEFAULT_THRESHOLD = 2;

    private int $totalShards;

    private int $threshold;

    public function __construct(
        private readonly HsmIntegrationService $hsm,
        private readonly EncryptionService $encryption,
        ?int $totalShards = null,
        ?int $threshold = null
    ) {
        $this->totalShards = $totalShards ?? config('keymanagement.shamir.total_shards', self::DEFAULT_TOTAL_SHARDS);
        $this->threshold = $threshold ?? config('keymanagement.shamir.threshold', self::DEFAULT_THRESHOLD);

        $this->validateConfiguration();
    }

    /**
     * @inheritDoc
     */
    public function splitKey(string $privateKey, string $userId): array
    {
        // Generate shards using Shamir's Secret Sharing algorithm
        $shards = $this->shamirSplit($privateKey, $this->totalShards, $this->threshold);

        return [
            'device' => new KeyShard(
                type: ShardType::DEVICE,
                data: $shards[0],
                encryptedFor: 'device-enclave',
                userId: $userId,
                index: 1
            ),
            'auth' => new KeyShard(
                type: ShardType::AUTH,
                data: $this->hsm->encrypt($shards[1]),
                encryptedFor: 'hsm',
                userId: $userId,
                index: 2
            ),
            'recovery' => new KeyShard(
                type: ShardType::RECOVERY,
                data: $this->encryption->encryptForUser($shards[2], $userId),
                encryptedFor: 'user-cloud',
                userId: $userId,
                index: 3
            ),
        ];
    }

    /**
     * @inheritDoc
     */
    public function reconstructKey(KeyShard $shard1, KeyShard $shard2): ReconstructedKey
    {
        $decryptedShards = [
            $this->decryptShard($shard1),
            $this->decryptShard($shard2),
        ];

        // Reconstruct using Shamir's algorithm
        $privateKey = $this->shamirRecover($decryptedShards);

        return new ReconstructedKey(
            privateKey: $privateKey,
            userId: $shard1->userId,
            reconstructedAt: new DateTimeImmutable(),
            ttlSeconds: config('keymanagement.key_ttl_seconds', 300)
        );
    }

    /**
     * @inheritDoc
     */
    public function verifyShards(array $shards, string $expectedPublicKey): bool
    {
        if (count($shards) < $this->threshold) {
            return false;
        }

        try {
            // Take first threshold number of shards
            $shardsToUse = array_slice($shards, 0, $this->threshold);
            $decryptedShards = array_map(
                fn (KeyShard $shard) => $this->decryptShard($shard),
                $shardsToUse
            );

            $reconstructedKey = $this->shamirRecover($decryptedShards);
            $derivedPublicKey = hash('sha256', $reconstructedKey);

            return hash_equals($expectedPublicKey, $derivedPublicKey);
        } catch (Exception) {
            return false;
        }
    }

    public function getThreshold(): int
    {
        return $this->threshold;
    }

    public function getTotalShards(): int
    {
        return $this->totalShards;
    }

    private function decryptShard(KeyShard $shard): string
    {
        return match ($shard->type) {
            ShardType::DEVICE   => $shard->data, // Already decrypted by device
            ShardType::AUTH     => $this->hsm->decrypt($shard->data),
            ShardType::RECOVERY => $this->encryption->decryptForUser($shard->data, $shard->userId),
        };
    }

    /**
     * Shamir's Secret Sharing - Split.
     *
     * @return array<string>
     */
    private function shamirSplit(string $secret, int $n, int $k): array
    {
        // Use GF(2^8) for simplicity
        $prime = 257; // Smallest prime > 256

        // Convert secret to bytes
        $secretBytes = array_values(unpack('C*', $secret) ?: []);

        $shards = array_fill(0, $n, []);

        foreach ($secretBytes as $byteIndex => $secretByte) {
            // Generate random coefficients for polynomial
            $coefficients = [$secretByte];
            for ($i = 1; $i < $k; $i++) {
                $coefficients[] = random_int(0, 255);
            }

            // Evaluate polynomial at points 1, 2, ..., n
            for ($x = 1; $x <= $n; $x++) {
                $y = 0;
                $xPow = 1;
                foreach ($coefficients as $coeff) {
                    $y = ($y + $coeff * $xPow) % $prime;
                    $xPow = ($xPow * $x) % $prime;
                }
                $shards[$x - 1][] = ['x' => $x, 'y' => $y];
            }
        }

        // Encode shards as base64 strings
        return array_map(
            fn (array $shard) => base64_encode(json_encode($shard) ?: '[]'),
            $shards
        );
    }

    /**
     * Shamir's Secret Sharing - Recover.
     *
     * @param array<string> $shards
     */
    private function shamirRecover(array $shards): string
    {
        $prime = 257;

        // Decode shards
        $decodedShards = array_map(
            fn (string $shard) => json_decode(base64_decode($shard), true),
            $shards
        );

        // Validate we have enough shards
        if (count($decodedShards) < 2) {
            throw new RuntimeException('Insufficient or invalid shards for recovery');
        }

        // Handle empty secret edge case (all shards are empty arrays)
        if (is_array($decodedShards[0]) && count($decodedShards[0]) === 0) {
            return '';
        }

        // Validate shard format
        if (! is_array($decodedShards[0]) || ! isset($decodedShards[0][0]['x'])) {
            throw new RuntimeException('Invalid shard format');
        }

        $secretBytes = [];
        $numBytes = count($decodedShards[0]);

        for ($byteIndex = 0; $byteIndex < $numBytes; $byteIndex++) {
            $points = [];
            foreach ($decodedShards as $shard) {
                $points[] = $shard[$byteIndex];
            }

            // Lagrange interpolation to find f(0)
            $secret = $this->lagrangeInterpolate(0, $points, $prime);
            $secretBytes[] = $secret;
        }

        return pack('C*', ...$secretBytes);
    }

    /**
     * Lagrange interpolation at x.
     *
     * @param array<array{x: int, y: int}> $points
     */
    private function lagrangeInterpolate(int $x, array $points, int $prime): int
    {
        $result = 0;

        for ($i = 0; $i < count($points); $i++) {
            $xi = $points[$i]['x'];
            $yi = $points[$i]['y'];

            $numerator = 1;
            $denominator = 1;

            for ($j = 0; $j < count($points); $j++) {
                if ($i !== $j) {
                    $xj = $points[$j]['x'];
                    $numerator = ($numerator * ($x - $xj)) % $prime;
                    $denominator = ($denominator * ($xi - $xj)) % $prime;
                }
            }

            // Handle negative modulo
            $numerator = (($numerator % $prime) + $prime) % $prime;
            $denominator = (($denominator % $prime) + $prime) % $prime;

            // Modular inverse
            $inverse = $this->modInverse($denominator, $prime);
            $term = ($yi * $numerator % $prime) * $inverse % $prime;

            $result = ($result + $term) % $prime;
        }

        return (($result % $prime) + $prime) % $prime;
    }

    /**
     * Extended Euclidean Algorithm for modular inverse.
     */
    private function modInverse(int $a, int $p): int
    {
        $m0 = $p;
        $y = 0;
        $x = 1;

        if ($p === 1) {
            return 0;
        }

        while ($a > 1) {
            $q = intdiv($a, $p);
            $t = $p;

            $p = $a % $p;
            $a = $t;
            $t = $y;

            $y = $x - $q * $y;
            $x = $t;
        }

        if ($x < 0) {
            $x += $m0;
        }

        return $x;
    }

    private function validateConfiguration(): void
    {
        if ($this->threshold < 2) {
            throw new InvalidArgumentException('Threshold must be at least 2');
        }

        if ($this->totalShards < $this->threshold) {
            throw new InvalidArgumentException('Total shards must be >= threshold');
        }

        if ($this->totalShards > 10) {
            throw new InvalidArgumentException('Total shards cannot exceed 10');
        }
    }
}
