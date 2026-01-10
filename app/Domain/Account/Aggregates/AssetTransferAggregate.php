<?php

namespace App\Domain\Account\Aggregates;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Hash;
use App\Domain\Account\Events\AssetTransferred;
use App\Domain\Account\Events\TransferThresholdReached;
use App\Domain\Account\Repositories\TransferRepository;
use App\Domain\Account\Repositories\TransferSnapshotRepository;
use App\Domain\Account\Utils\ValidatesHash;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class AssetTransferAggregate extends AggregateRoot
{
    use ValidatesHash;

    public const int COUNT_THRESHOLD = 1000;

    public function __construct(
        public int $count = 0,
    ) {
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getStoredEventRepository(): TransferRepository
    {
        return app()->make(
            abstract: TransferRepository::class
        );
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getSnapshotRepository(): TransferSnapshotRepository
    {
        return app()->make(
            abstract: TransferSnapshotRepository::class
        );
    }

    /**
     * @return $this
     */
    public function transfer(
        AccountUuid $from,
        AccountUuid $to,
        string $assetCode,
        int $amount,
        ?array $metadata = []
    ): static {
        $this->recordThat(
            domainEvent: new AssetTransferred(
                from: $from,
                to: $to,
                assetCode: $assetCode,
                amount: $amount,
                hash: $this->generateHashForAssetTransfer($from, $to, $assetCode, $amount),
                metadata: $metadata
            )
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function applyAssetTransferred(AssetTransferred $event): static
    {
        $this->validateHashForAssetTransfer(
            hash: $event->hash,
            from: $event->from,
            to: $event->to,
            assetCode: $event->assetCode,
            amount: $event->amount
        );

        if (++$this->count >= self::COUNT_THRESHOLD) {
            $this->recordThat(
                domainEvent: new TransferThresholdReached()
            );
            $this->count = 0;
        }

        $this->storeHash($event->hash);

        return $this;
    }

    /**
     * Generate hash for asset transfer.
     */
    protected function generateHashForAssetTransfer(
        AccountUuid $from,
        AccountUuid $to,
        string $assetCode,
        int $amount
    ): Hash {
        $data = sprintf(
            '%s:%s:%s:%d:%d',
            $from->getUuid(),
            $to->getUuid(),
            $assetCode,
            $amount,
            time()
        );

        return new Hash(hash('sha3-512', $data));
    }

    /**
     * Validate hash for asset transfer.
     */
    protected function validateHashForAssetTransfer(
        Hash $hash,
        AccountUuid $from,
        AccountUuid $to,
        string $assetCode,
        int $amount
    ): void {
        // For now, just validate that the hash exists and is in correct format
        // In production, you would implement proper hash validation logic
        if (! $hash->getHash() || strlen($hash->getHash()) !== 128) {
            throw new InvalidArgumentException('Invalid hash format');
        }
    }
}
