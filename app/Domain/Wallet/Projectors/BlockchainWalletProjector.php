<?php

namespace App\Domain\Wallet\Projectors;

use App\Domain\Wallet\Events\BlockchainWalletCreated;
use App\Domain\Wallet\Events\WalletAddressGenerated;
use App\Domain\Wallet\Events\WalletSettingsUpdated;
use App\Domain\Wallet\Events\WalletFrozen;
use App\Domain\Wallet\Events\WalletUnfrozen;
use App\Domain\Wallet\Events\WalletKeyRotated;
use App\Domain\Wallet\Events\WalletBackupCreated;
use Illuminate\Support\Facades\DB;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class BlockchainWalletProjector extends Projector
{
    public function onBlockchainWalletCreated(BlockchainWalletCreated $event): void
    {
        DB::table('blockchain_wallets')->insert([
            'wallet_id' => $event->walletId,
            'user_id' => $event->userId,
            'type' => $event->type,
            'status' => 'active',
            'settings' => json_encode($event->settings),
            'metadata' => json_encode(['master_public_key' => $event->masterPublicKey]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    public function onWalletAddressGenerated(WalletAddressGenerated $event): void
    {
        DB::table('wallet_addresses')->insert([
            'wallet_id' => $event->walletId,
            'chain' => $event->chain,
            'address' => $event->address,
            'public_key' => $event->publicKey,
            'derivation_path' => $event->derivationPath,
            'label' => $event->label,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    public function onWalletSettingsUpdated(WalletSettingsUpdated $event): void
    {
        DB::table('blockchain_wallets')
            ->where('wallet_id', $event->walletId)
            ->update([
                'settings' => json_encode($event->newSettings),
                'updated_at' => now(),
            ]);
    }
    
    public function onWalletFrozen(WalletFrozen $event): void
    {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $event->walletId)
            ->first();
        
        $metadata = json_decode($wallet->metadata, true) ?? [];
        $metadata['freeze_reason'] = $event->reason;
        $metadata['frozen_by'] = $event->frozenBy;
        $metadata['frozen_at'] = $event->frozenAt->toDateTimeString();
        
        DB::table('blockchain_wallets')
            ->where('wallet_id', $event->walletId)
            ->update([
                'status' => 'frozen',
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ]);
    }
    
    public function onWalletUnfrozen(WalletUnfrozen $event): void
    {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $event->walletId)
            ->first();
        
        $metadata = json_decode($wallet->metadata, true) ?? [];
        unset($metadata['freeze_reason']);
        unset($metadata['frozen_by']);
        unset($metadata['frozen_at']);
        
        DB::table('blockchain_wallets')
            ->where('wallet_id', $event->walletId)
            ->update([
                'status' => 'active',
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ]);
    }
    
    public function onWalletKeyRotated(WalletKeyRotated $event): void
    {
        // Update the public key for addresses on this chain
        DB::table('wallet_addresses')
            ->where('wallet_id', $event->walletId)
            ->where('chain', $event->chain)
            ->update([
                'public_key' => $event->newPublicKey,
                'updated_at' => now(),
            ]);
        
        // Log the key rotation in wallet metadata
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $event->walletId)
            ->first();
        
        $metadata = json_decode($wallet->metadata, true) ?? [];
        $metadata['last_key_rotation'] = [
            'chain' => $event->chain,
            'rotated_by' => $event->rotatedBy,
            'rotated_at' => $event->rotatedAt->toDateTimeString(),
        ];
        
        DB::table('blockchain_wallets')
            ->where('wallet_id', $event->walletId)
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ]);
    }
    
    public function onWalletBackupCreated(WalletBackupCreated $event): void
    {
        DB::table('wallet_backups')->insert([
            'wallet_id' => $event->walletId,
            'backup_id' => $event->backupId,
            'backup_method' => $event->backupMethod,
            'encrypted_data' => $event->encryptedData,
            'checksum' => hash('sha256', $event->encryptedData),
            'created_by' => $event->createdBy,
            'created_at' => $event->createdAt,
            'updated_at' => $event->createdAt,
        ]);
        
        // Update wallet metadata
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $event->walletId)
            ->first();
        
        $metadata = json_decode($wallet->metadata, true) ?? [];
        $metadata['last_backup'] = [
            'backup_id' => $event->backupId,
            'method' => $event->backupMethod,
            'created_by' => $event->createdBy,
            'created_at' => $event->createdAt->toDateTimeString(),
        ];
        
        DB::table('blockchain_wallets')
            ->where('wallet_id', $event->walletId)
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ]);
    }
}