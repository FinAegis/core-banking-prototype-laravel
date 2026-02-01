<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\HSM;

use App\Domain\KeyManagement\Contracts\HsmProviderInterface;
use RuntimeException;

class HsmIntegrationService
{
    private HsmProviderInterface $provider;

    public function __construct(?HsmProviderInterface $provider = null)
    {
        $this->provider = $provider ?? $this->resolveProvider();
    }

    public function encrypt(string $data): string
    {
        $this->ensureAvailable();

        return $this->provider->encrypt($data, $this->getKeyId());
    }

    public function decrypt(string $encryptedData): string
    {
        $this->ensureAvailable();

        return $this->provider->decrypt($encryptedData, $this->getKeyId());
    }

    public function store(string $secretId, string $data): bool
    {
        $this->ensureAvailable();

        return $this->provider->store($secretId, $data);
    }

    public function retrieve(string $secretId): ?string
    {
        $this->ensureAvailable();

        return $this->provider->retrieve($secretId);
    }

    public function delete(string $secretId): bool
    {
        $this->ensureAvailable();

        return $this->provider->delete($secretId);
    }

    public function isAvailable(): bool
    {
        return $this->provider->isAvailable();
    }

    public function getProviderName(): string
    {
        return $this->provider->getProviderName();
    }

    private function resolveProvider(): HsmProviderInterface
    {
        $providerType = config('keymanagement.hsm.provider', 'demo');

        return match ($providerType) {
            'demo'  => new DemoHsmProvider(),
            'aws'   => throw new RuntimeException('AWS KMS provider not yet implemented'),
            'azure' => throw new RuntimeException('Azure Key Vault provider not yet implemented'),
            default => throw new RuntimeException("Unknown HSM provider: {$providerType}"),
        };
    }

    private function getKeyId(): string
    {
        return config('keymanagement.hsm.key_id', 'default');
    }

    private function ensureAvailable(): void
    {
        if (! $this->provider->isAvailable()) {
            throw new RuntimeException(
                "HSM provider '{$this->provider->getProviderName()}' is not available"
            );
        }
    }
}
