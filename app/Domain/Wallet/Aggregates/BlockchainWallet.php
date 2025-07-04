<?php

namespace App\Domain\Wallet\Aggregates;

use App\Domain\Wallet\Events\BlockchainWalletCreated;
use App\Domain\Wallet\Events\WalletAddressGenerated;
use App\Domain\Wallet\Events\WalletSettingsUpdated;
use App\Domain\Wallet\Events\WalletFrozen;
use App\Domain\Wallet\Events\WalletUnfrozen;
use App\Domain\Wallet\Events\WalletKeyRotated;
use App\Domain\Wallet\Events\WalletBackupCreated;
use App\Domain\Wallet\Exceptions\WalletException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class BlockchainWallet extends AggregateRoot
{
    protected string $walletId;
    protected string $userId;
    protected string $type; // 'custodial', 'non-custodial', 'smart-contract'
    protected array $addresses = []; // chain => [addresses]
    protected array $publicKeys = []; // chain => public key
    protected string $status = 'active'; // 'active', 'frozen', 'closed'
    protected array $settings = [];
    protected ?string $masterPublicKey = null;
    protected array $metadata = [];
    
    public static function create(
        string $walletId,
        string $userId,
        string $type,
        ?string $masterPublicKey = null,
        array $settings = []
    ): self {
        if (!in_array($type, ['custodial', 'non-custodial', 'smart-contract'])) {
            throw new WalletException("Invalid wallet type: {$type}");
        }
        
        $wallet = static::retrieve($walletId);
        
        $wallet->recordThat(new BlockchainWalletCreated(
            walletId: $walletId,
            userId: $userId,
            type: $type,
            masterPublicKey: $masterPublicKey,
            settings: $settings
        ));
        
        return $wallet;
    }
    
    public function generateAddress(
        string $chain,
        string $address,
        string $publicKey,
        string $derivationPath,
        ?string $label = null
    ): self {
        if ($this->status !== 'active') {
            throw new WalletException('Cannot generate address for inactive wallet');
        }
        
        if (isset($this->addresses[$chain]) && in_array($address, $this->addresses[$chain])) {
            throw new WalletException("Address already exists for chain {$chain}");
        }
        
        $this->recordThat(new WalletAddressGenerated(
            walletId: $this->walletId,
            chain: $chain,
            address: $address,
            publicKey: $publicKey,
            derivationPath: $derivationPath,
            label: $label
        ));
        
        return $this;
    }
    
    public function updateSettings(array $settings): self
    {
        $allowedSettings = [
            'withdrawal_whitelist' => 'array',
            'daily_limit' => 'numeric',
            'requires_2fa' => 'boolean',
            'auto_convert' => 'boolean',
            'notification_email' => 'string',
            'webhook_url' => 'string'
        ];
        
        foreach ($settings as $key => $value) {
            if (!isset($allowedSettings[$key])) {
                throw new WalletException("Invalid setting: {$key}");
            }
        }
        
        $this->recordThat(new WalletSettingsUpdated(
            walletId: $this->walletId,
            oldSettings: $this->settings,
            newSettings: array_merge($this->settings, $settings)
        ));
        
        return $this;
    }
    
    public function freeze(string $reason, string $frozenBy): self
    {
        if ($this->status === 'frozen') {
            throw new WalletException('Wallet is already frozen');
        }
        
        if ($this->status === 'closed') {
            throw new WalletException('Cannot freeze closed wallet');
        }
        
        $this->recordThat(new WalletFrozen(
            walletId: $this->walletId,
            reason: $reason,
            frozenBy: $frozenBy,
            frozenAt: now()
        ));
        
        return $this;
    }
    
    public function unfreeze(string $unfrozenBy): self
    {
        if ($this->status !== 'frozen') {
            throw new WalletException('Wallet is not frozen');
        }
        
        $this->recordThat(new WalletUnfrozen(
            walletId: $this->walletId,
            unfrozenBy: $unfrozenBy,
            unfrozenAt: now()
        ));
        
        return $this;
    }
    
    public function rotateKey(
        string $chain,
        string $oldPublicKey,
        string $newPublicKey,
        string $rotatedBy
    ): self {
        if ($this->status !== 'active') {
            throw new WalletException('Cannot rotate key for inactive wallet');
        }
        
        if (!isset($this->publicKeys[$chain]) || $this->publicKeys[$chain] !== $oldPublicKey) {
            throw new WalletException('Old public key does not match');
        }
        
        $this->recordThat(new WalletKeyRotated(
            walletId: $this->walletId,
            chain: $chain,
            oldPublicKey: $oldPublicKey,
            newPublicKey: $newPublicKey,
            rotatedBy: $rotatedBy,
            rotatedAt: now()
        ));
        
        return $this;
    }
    
    public function createBackup(
        string $backupId,
        string $encryptedData,
        string $backupMethod,
        string $createdBy
    ): self {
        if ($this->type !== 'non-custodial') {
            throw new WalletException('Backup only available for non-custodial wallets');
        }
        
        $this->recordThat(new WalletBackupCreated(
            walletId: $this->walletId,
            backupId: $backupId,
            backupMethod: $backupMethod,
            encryptedData: $encryptedData,
            createdBy: $createdBy,
            createdAt: now()
        ));
        
        return $this;
    }
    
    // Apply event methods
    protected function applyBlockchainWalletCreated(BlockchainWalletCreated $event): void
    {
        $this->walletId = $event->walletId;
        $this->userId = $event->userId;
        $this->type = $event->type;
        $this->masterPublicKey = $event->masterPublicKey;
        $this->settings = $event->settings;
        $this->status = 'active';
    }
    
    protected function applyWalletAddressGenerated(WalletAddressGenerated $event): void
    {
        if (!isset($this->addresses[$event->chain])) {
            $this->addresses[$event->chain] = [];
        }
        
        $this->addresses[$event->chain][] = [
            'address' => $event->address,
            'derivation_path' => $event->derivationPath,
            'label' => $event->label,
            'created_at' => now()->toDateTimeString()
        ];
        
        $this->publicKeys[$event->chain] = $event->publicKey;
    }
    
    protected function applyWalletSettingsUpdated(WalletSettingsUpdated $event): void
    {
        $this->settings = $event->newSettings;
    }
    
    protected function applyWalletFrozen(WalletFrozen $event): void
    {
        $this->status = 'frozen';
        $this->metadata['freeze_reason'] = $event->reason;
        $this->metadata['frozen_by'] = $event->frozenBy;
        $this->metadata['frozen_at'] = $event->frozenAt->toDateTimeString();
    }
    
    protected function applyWalletUnfrozen(WalletUnfrozen $event): void
    {
        $this->status = 'active';
        unset($this->metadata['freeze_reason']);
        unset($this->metadata['frozen_by']);
        unset($this->metadata['frozen_at']);
    }
    
    protected function applyWalletKeyRotated(WalletKeyRotated $event): void
    {
        $this->publicKeys[$event->chain] = $event->newPublicKey;
    }
    
    protected function applyWalletBackupCreated(WalletBackupCreated $event): void
    {
        $this->metadata['last_backup'] = [
            'backup_id' => $event->backupId,
            'method' => $event->backupMethod,
            'created_by' => $event->createdBy,
            'created_at' => $event->createdAt->toDateTimeString()
        ];
    }
    
    // Getters
    public function getWalletId(): string
    {
        return $this->walletId;
    }
    
    public function getUserId(): string
    {
        return $this->userId;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function getStatus(): string
    {
        return $this->status;
    }
    
    public function getAddresses(string $chain = null): array
    {
        if ($chain) {
            return $this->addresses[$chain] ?? [];
        }
        
        return $this->addresses;
    }
    
    public function getSettings(): array
    {
        return $this->settings;
    }
    
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
}