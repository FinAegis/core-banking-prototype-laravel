<?php

namespace App\Domain\Account\Aggregates;

use App\Domain\Account\DataObjects\Hash;
use App\Domain\Account\Events\AccountLimitHit;
use App\Domain\Account\Events\AssetBalanceAdded;
use App\Domain\Account\Events\AssetBalanceSubtracted;
use App\Domain\Account\Events\TransactionThresholdReached;
use App\Domain\Account\Exceptions\InvalidHashException;
use App\Domain\Account\Exceptions\NotEnoughFunds;
use App\Domain\Account\Repositories\TransactionRepository;
use App\Domain\Account\Repositories\TransactionSnapshotRepository;
use App\Domain\Account\Utils\ValidatesHash;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class AssetTransactionAggregate extends AggregateRoot
{
    use ValidatesHash;

    protected const int ACCOUNT_LIMIT = 0;

    public const int COUNT_THRESHOLD = 1000;

    /**
     * @var array<string, int>
     */
    protected array $balances = [];

    public int $count = 0;

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getStoredEventRepository(): TransactionRepository
    {
        return app()->make(
            abstract: TransactionRepository::class
        );
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getSnapshotRepository(): TransactionSnapshotRepository
    {
        return app()->make(
            abstract: TransactionSnapshotRepository::class
        );
    }

    /**
     * @return $this
     */
    public function credit(string $assetCode, int $amount): static
    {
        $this->recordThat(
            domainEvent: new AssetBalanceAdded(
                assetCode: $assetCode,
                amount: $amount,
                hash: $this->generateHashForAsset($assetCode, $amount),
            )
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function applyAssetBalanceAdded(AssetBalanceAdded $event): static
    {
        $this->validateHashForAsset(
            hash: $event->hash,
            assetCode: $event->assetCode,
            amount: $event->amount
        );

        if (! isset($this->balances[$event->assetCode])) {
            $this->balances[$event->assetCode] = 0;
        }

        $this->balances[$event->assetCode] += $event->amount;

        if (++$this->count >= self::COUNT_THRESHOLD) {
            $this->recordThat(
                domainEvent: new TransactionThresholdReached()
            );
            $this->count = 0;
        }

        $this->storeHash($event->hash);

        return $this;
    }

    /**
     * @return $this
     */
    public function debit(string $assetCode, int $amount): static
    {
        if (! $this->hasSufficientFundsToSubtractAmount($assetCode, $amount)) {
            $this->recordThat(
                new AccountLimitHit()
            );

            $this->persist();

            throw new NotEnoughFunds("Insufficient balance for asset: {$assetCode}");
        }

        $this->recordThat(
            new AssetBalanceSubtracted(
                assetCode: $assetCode,
                amount: $amount,
                hash: $this->generateHashForAsset($assetCode, $amount)
            )
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function applyAssetBalanceSubtracted(AssetBalanceSubtracted $event): static
    {
        $this->validateHashForAsset(
            hash: $event->hash,
            assetCode: $event->assetCode,
            amount: $event->amount
        );

        if (! isset($this->balances[$event->assetCode])) {
            $this->balances[$event->assetCode] = 0;
        }

        $this->balances[$event->assetCode] -= $event->amount;

        $this->storeHash($event->hash);

        return $this;
    }

    protected function hasSufficientFundsToSubtractAmount(string $assetCode, int $amount): bool
    {
        $balance = $this->balances[$assetCode] ?? 0;

        return $balance - $amount >= self::ACCOUNT_LIMIT;
    }

    public function getBalance(string $assetCode): int
    {
        return $this->balances[$assetCode] ?? 0;
    }

    /**
     * @return array<string, int>
     */
    public function getAllBalances(): array
    {
        return $this->balances;
    }

    /**
     * Generate hash for asset transaction.
     */
    protected function generateHashForAsset(string $assetCode, int $amount): Hash
    {
        $data = sprintf('%s:%d:%d', $assetCode, $amount, time());

        return new Hash(hash('sha3-512', $data));
    }

    /**
     * Validate hash for asset transaction.
     *
     * @throws InvalidHashException
     */
    protected function validateHashForAsset(Hash $hash, string $assetCode, int $amount): void
    {
        // For now, just validate that the hash exists and is in correct format
        // In production, you would implement proper hash validation logic
        if (! $hash->getHash() || strlen($hash->getHash()) !== 128) {
            throw new InvalidHashException('Invalid hash format');
        }
    }
}
