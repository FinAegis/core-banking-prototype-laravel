<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Events\AgentTransactionInitiated;
use App\Domain\AgentProtocol\Events\AgentWalletCreated;
use App\Domain\AgentProtocol\Events\PaymentReceived;
use App\Domain\AgentProtocol\Events\PaymentSent;
use App\Domain\AgentProtocol\Events\WalletBalanceUpdated;
use App\Domain\AgentProtocol\Repositories\AgentProtocolEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentProtocolSnapshotRepository;
use DomainException;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class AgentWalletAggregate extends AggregateRoot
{
    protected string $walletId = '';

    protected string $agentId = '';

    protected string $currency = 'USD';

    protected float $balance = 0.0;

    protected float $availableBalance = 0.0;

    protected float $heldBalance = 0.0;

    protected array $transactions = [];

    protected array $metadata = [];

    protected string $status = 'active';

    protected array $limits = [];

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(AgentProtocolEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(AgentProtocolSnapshotRepository::class);
    }

    public static function create(
        string $walletId,
        string $agentId,
        string $currency = 'USD',
        float $initialBalance = 0.0,
        array $metadata = []
    ): self {
        $aggregate = static::retrieve($walletId);

        $aggregate->recordThat(new AgentWalletCreated(
            walletId: $walletId,
            agentId: $agentId,
            currency: $currency,
            initialBalance: $initialBalance,
            metadata: $metadata
        ));

        if ($initialBalance > 0) {
            $aggregate->recordThat(new WalletBalanceUpdated(
                walletId: $walletId,
                previousBalance: 0.0,
                newBalance: $initialBalance,
                change: $initialBalance,
                reason: 'initial_deposit',
                metadata: ['source' => 'system']
            ));
        }

        return $aggregate;
    }

    public function initiatePayment(
        string $transactionId,
        string $toAgentId,
        float $amount,
        string $type = 'transfer',
        array $metadata = []
    ): self {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be positive');
        }

        if ($amount > $this->availableBalance) {
            throw new DomainException('Insufficient balance');
        }

        $this->recordThat(new AgentTransactionInitiated(
            transactionId: $transactionId,
            fromAgentId: $this->agentId,
            toAgentId: $toAgentId,
            amount: $amount,
            currency: $this->currency,
            type: $type,
            status: 'pending',
            metadata: $metadata
        ));

        // Hold the amount
        $this->recordThat(new WalletBalanceUpdated(
            walletId: $this->walletId,
            previousBalance: $this->balance,
            newBalance: $this->balance,
            change: 0.0,
            reason: 'payment_hold',
            metadata: [
                'held_amount'    => $amount,
                'transaction_id' => $transactionId,
            ]
        ));

        return $this;
    }

    public function completePayment(
        string $transactionId,
        float $amount,
        string $toAgentId,
        array $metadata = []
    ): self {
        if (! isset($this->transactions[$transactionId])) {
            throw new DomainException('Transaction not found');
        }

        $this->recordThat(new PaymentSent(
            walletId: $this->walletId,
            transactionId: $transactionId,
            toAgentId: $toAgentId,
            amount: $amount,
            currency: $this->currency,
            metadata: $metadata
        ));

        $this->recordThat(new WalletBalanceUpdated(
            walletId: $this->walletId,
            previousBalance: $this->balance,
            newBalance: $this->balance - $amount,
            change: -$amount,
            reason: 'payment_completed',
            metadata: ['transaction_id' => $transactionId]
        ));

        return $this;
    }

    public function receivePayment(
        string $transactionId,
        string $fromAgentId,
        float $amount,
        array $metadata = []
    ): self {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be positive');
        }

        $this->recordThat(new PaymentReceived(
            walletId: $this->walletId,
            transactionId: $transactionId,
            fromAgentId: $fromAgentId,
            amount: $amount,
            currency: $this->currency,
            metadata: $metadata
        ));

        $this->recordThat(new WalletBalanceUpdated(
            walletId: $this->walletId,
            previousBalance: $this->balance,
            newBalance: $this->balance + $amount,
            change: $amount,
            reason: 'payment_received',
            metadata: ['transaction_id' => $transactionId]
        ));

        return $this;
    }

    public function holdFunds(float $amount, string $reason, array $metadata = []): self
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Hold amount must be positive');
        }

        if ($this->availableBalance < $amount) {
            throw new InvalidArgumentException('Insufficient available balance');
        }

        $this->recordThat(new WalletBalanceUpdated(
            walletId: $this->walletId,
            previousBalance: $this->balance,
            newBalance: $this->balance,
            change: 0,
            reason: 'funds_held: ' . $reason,
            metadata: array_merge($metadata, [
                'held_amount'       => $amount,
                'available_balance' => $this->availableBalance - $amount,
                'held_balance'      => $this->heldBalance + $amount,
            ])
        ));

        return $this;
    }

    public function releaseFunds(float $amount, string $reason, array $metadata = []): self
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Release amount must be positive');
        }

        if ($this->heldBalance < $amount) {
            throw new InvalidArgumentException('Insufficient held balance');
        }

        $this->recordThat(new WalletBalanceUpdated(
            walletId: $this->walletId,
            previousBalance: $this->balance,
            newBalance: $this->balance,
            change: 0,
            reason: 'funds_released: ' . $reason,
            metadata: array_merge($metadata, [
                'released_amount'   => $amount,
                'available_balance' => $this->availableBalance + $amount,
                'held_balance'      => $this->heldBalance - $amount,
            ])
        ));

        return $this;
    }

    protected function applyAgentWalletCreated(AgentWalletCreated $event): void
    {
        $this->walletId = $event->walletId;
        $this->agentId = $event->agentId;
        $this->currency = $event->currency;
        $this->balance = $event->initialBalance;
        $this->availableBalance = $event->initialBalance;
        $this->metadata = $event->metadata;
        $this->status = 'active';

        // Set default limits
        $this->limits = [
            'daily_transaction' => 100000.0,
            'per_transaction'   => 10000.0,
            'daily_withdrawal'  => 50000.0,
        ];
    }

    protected function applyWalletBalanceUpdated(WalletBalanceUpdated $event): void
    {
        $this->balance = $event->newBalance;

        if ($event->reason === 'payment_hold') {
            $heldAmount = $event->metadata['held_amount'] ?? 0.0;
            $this->heldBalance += $heldAmount;
            $this->availableBalance = $this->balance - $this->heldBalance;
        } elseif ($event->reason === 'payment_completed') {
            $transactionId = $event->metadata['transaction_id'] ?? '';
            if (isset($this->transactions[$transactionId])) {
                $heldAmount = $this->transactions[$transactionId]['amount'] ?? 0.0;
                $this->heldBalance -= $heldAmount;
            }
            $this->availableBalance = $this->balance - $this->heldBalance;
        } elseif (str_starts_with($event->reason, 'funds_held:')) {
            // Handle funds being held
            $this->availableBalance = $event->metadata['available_balance'] ?? $this->availableBalance;
            $this->heldBalance = $event->metadata['held_balance'] ?? $this->heldBalance;
        } elseif (str_starts_with($event->reason, 'funds_released:')) {
            // Handle funds being released
            $this->availableBalance = $event->metadata['available_balance'] ?? $this->availableBalance;
            $this->heldBalance = $event->metadata['held_balance'] ?? $this->heldBalance;
        } else {
            $this->availableBalance = $this->balance - $this->heldBalance;
        }
    }

    protected function applyAgentTransactionInitiated(AgentTransactionInitiated $event): void
    {
        $this->transactions[$event->transactionId] = [
            'to_agent_id'  => $event->toAgentId,
            'amount'       => $event->amount,
            'type'         => $event->type,
            'status'       => $event->status,
            'initiated_at' => now()->toIso8601String(),
            'metadata'     => $event->metadata,
        ];
    }

    protected function applyPaymentSent(PaymentSent $event): void
    {
        if (isset($this->transactions[$event->transactionId])) {
            $this->transactions[$event->transactionId]['status'] = 'completed';
            $this->transactions[$event->transactionId]['completed_at'] = now()->toIso8601String();
        }
    }

    protected function applyPaymentReceived(PaymentReceived $event): void
    {
        $this->transactions[$event->transactionId] = [
            'from_agent_id' => $event->fromAgentId,
            'amount'        => $event->amount,
            'type'          => 'received',
            'status'        => 'completed',
            'received_at'   => now()->toIso8601String(),
            'metadata'      => $event->metadata,
        ];
    }

    public function getWalletId(): string
    {
        return $this->walletId;
    }

    public function getAgentId(): string
    {
        return $this->agentId;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getAvailableBalance(): float
    {
        return $this->availableBalance;
    }

    public function getHeldBalance(): float
    {
        return $this->heldBalance;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hassufficientBalance(float $amount): bool
    {
        return $this->availableBalance >= $amount;
    }

    public function isWithinLimit(string $limitType, float $amount): bool
    {
        $limit = $this->limits[$limitType] ?? PHP_FLOAT_MAX;

        return $amount <= $limit;
    }
}
