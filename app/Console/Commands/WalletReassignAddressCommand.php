<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\User\Values\UserRoles;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Operator recovery when a wallet address was claimed by the wrong account.
 *
 * POST /api/v1/wallet/addresses keeps the first claim of an address, so if
 * another account registered a user's address first, the real owner's app
 * gets a generic 422 and has no balances, sends or inbound monitoring. The
 * registrar logs a critical "Privy-verified owner blocked" line when Privy
 * confirms the caller owns the address. This command moves every row of the
 * address (all EVM chains, or the Solana row) to the rightful user.
 *
 * The address itself does not change, so the Helius / Alchemy watch lists
 * stay as they are; only the cached address-to-user mapping is cleared.
 */
class WalletReassignAddressCommand extends Command
{
    /** @var string */
    protected $signature = 'wallet:reassign-address
                            {--address= : The EVM (0x…) or Solana (base58) address to reassign}
                            {--to-user= : Email of the user who owns the address}
                            {--operator-email= : Admin operator email authorising the reassignment}
                            {--confirm : Required — confirms the reassignment}
                            {--allow-production : Required when APP_ENV=production}';

    /** @var string */
    protected $description = 'Move a mis-claimed wallet address to its rightful owner (operator recovery)';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production guard: --allow-production is required when APP_ENV=production.');

            return self::FAILURE;
        }

        if (! $this->option('confirm')) {
            $this->error('--confirm is required: this moves the address and its inbound attribution to another user.');

            return self::FAILURE;
        }

        $address = trim((string) $this->option('address'));
        $toUserEmail = (string) $this->option('to-user');
        $operatorEmail = (string) $this->option('operator-email');

        if ($address === '' || $toUserEmail === '' || $operatorEmail === '') {
            $this->error('--address, --to-user and --operator-email are all required.');

            return self::FAILURE;
        }

        // EVM addresses are stored lowercase; Solana addresses are case-sensitive.
        if (preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1) {
            $address = strtolower($address);
        }

        $operator = User::where('email', $operatorEmail)->first();
        if ($operator === null || ! $operator->hasRole(UserRoles::ADMIN->value)) {
            $this->error("Operator {$operatorEmail} not found or not an admin.");

            return self::FAILURE;
        }

        $targetUser = User::where('email', $toUserEmail)->first();
        if ($targetUser === null) {
            $this->error("Target user {$toUserEmail} not found.");

            return self::FAILURE;
        }

        /** @var array<int, array{chain: string, from: string}> $moved */
        $moved = DB::transaction(function () use ($address, $targetUser, $operator): array {
            $rows = BlockchainAddress::query()
                ->where('address', $address)
                ->lockForUpdate()
                ->get();

            $moved = [];

            foreach ($rows as $row) {
                if ($row->user_uuid === $targetUser->uuid) {
                    continue;
                }

                $from = $row->user_uuid;
                $moved[] = ['chain' => $row->chain, 'from' => $from];

                $row->user_uuid = $targetUser->uuid;
                $row->is_active = true;
                $row->metadata = array_merge($row->metadata ?? [], [
                    'reassigned_from_user_uuid' => $from,
                    'reassigned_by_operator_id' => $operator->id,
                    'reassigned_at'             => now()->toIso8601String(),
                ]);
                $row->save();
            }

            return $moved;
        });

        if ($moved === []) {
            $this->warn("No rows to move: {$address} is not registered, or already belongs to {$targetUser->email}.");

            return self::SUCCESS;
        }

        // ProcessAlchemyWebhookJob caches address → user for up to an hour.
        Cache::forget("webhook:addr_to_user:{$address}");

        Log::warning('wallet:reassign-address moved a wallet address to another user', [
            'address'     => $address,
            'to_user_id'  => $targetUser->id,
            'operator_id' => $operator->id,
            'moved'       => $moved,
        ]);

        foreach ($moved as $row) {
            $this->line("{$row['chain']}: moved from user {$row['from']}");
        }

        $this->info("{$address} now belongs to {$targetUser->email} (user #{$targetUser->id}).");

        return self::SUCCESS;
    }
}
