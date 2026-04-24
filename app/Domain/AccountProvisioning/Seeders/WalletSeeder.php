<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Seeders;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Helpers\SolanaAddressHelper;
use App\Models\User;

/**
 * Seeds deterministic wallet addresses for a review/demo account.
 *
 * Creates one Polygon (EVM) and one Solana BlockchainAddress per user.
 * Solana uses the canonical SolanaAddressHelper::deriveForUser() derivation.
 * Polygon uses a deterministic sha256-derived placeholder mirroring the
 * MobileWalletController onboarding fallback — review accounts don't need
 * a real signable EVM key, just a visible address the UI can render.
 */
class WalletSeeder
{
    public function seed(User $user): void
    {
        $appKey = (string) config('app.key');

        // Polygon (EVM) — deterministic placeholder address.
        // Review-account seeding shortcut — does not go through SmartAccountService.
        $polygonAddress = '0x' . substr(
            hash('sha256', "wallet:{$user->id}:{$appKey}:polygon"),
            0,
            40
        );

        BlockchainAddress::firstOrCreate(
            ['address' => $polygonAddress, 'chain' => 'polygon'],
            [
                'user_uuid'       => $user->uuid,
                'public_key'      => $polygonAddress,
                'is_active'       => true,
                'label'           => 'Primary Polygon',
                'derivation_path' => "m/44'/60'/0'/0/0",
            ]
        );

        // Solana — canonical ed25519 derivation.
        $solanaAddress = SolanaAddressHelper::deriveForUser($user->id, $appKey);

        BlockchainAddress::firstOrCreate(
            ['address' => $solanaAddress, 'chain' => 'solana'],
            [
                'user_uuid'       => $user->uuid,
                'public_key'      => $solanaAddress,
                'is_active'       => true,
                'label'           => 'Primary Solana',
                'derivation_path' => "m/44'/501'/0'/0'",
            ]
        );
    }
}
