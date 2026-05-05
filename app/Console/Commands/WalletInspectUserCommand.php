<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Console\Command;

/**
 * Operator query: print everything wallet-related for a single user.
 *
 * During the Privy cutover this is the first thing to reach for when a
 * mobile user reports "my send is stuck" or "the app says no addresses
 * registered." Reads only — never writes.
 *
 *   php artisan wallet:inspect-user user@example.com
 *   php artisan wallet:inspect-user user@example.com --sends=20
 */
class WalletInspectUserCommand extends Command
{
    protected $signature = 'wallet:inspect-user
        {email : User email to look up}
        {--sends=10 : How many recent wallet_send_records to print}';

    protected $description = 'Print Privy linkage, registered addresses, and recent send records for a user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $this->error(sprintf('No user with email "%s".', $email));

            return self::FAILURE;
        }

        $this->printUserSection($user);
        $this->newLine();
        $this->printAddressesSection($user);
        $this->newLine();
        $this->printSendsSection($user, (int) $this->option('sends'));

        return self::SUCCESS;
    }

    private function printUserSection(User $user): void
    {
        $this->info('User');
        $this->table(
            ['Field', 'Value'],
            [
                ['id', (string) $user->id],
                ['uuid', $user->uuid],
                ['email', $user->email],
                ['privy_user_id', $user->privy_user_id ?? '(not linked)'],
                ['privy_linked_at', $this->formatTimestamp($user->privy_linked_at)],
                ['created_at', $this->formatTimestamp($user->created_at)],
            ],
        );
    }

    private function formatTimestamp(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(\DATE_ATOM);
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return '(never)';
    }

    private function printAddressesSection(User $user): void
    {
        $this->info('Registered blockchain addresses');

        $addresses = BlockchainAddress::query()
            ->where('user_uuid', $user->uuid)
            ->orderBy('chain')
            ->get();

        if ($addresses->isEmpty()) {
            $this->warn('  (none — user has not posted to /v1/wallet/addresses yet)');

            return;
        }

        $rows = $addresses->map(function (BlockchainAddress $a): array {
            $metadata = $a->metadata ?? [];
            $provider = is_array($metadata) && isset($metadata['provider']) && is_string($metadata['provider'])
                ? $metadata['provider']
                : '-';
            $kind = is_array($metadata) && isset($metadata['wallet_kind']) && is_string($metadata['wallet_kind'])
                ? $metadata['wallet_kind']
                : '-';

            return [
                $a->chain,
                $a->address,
                $a->is_active ? 'yes' : 'no',
                $provider,
                $kind,
                $a->label ?? '-',
            ];
        })->toArray();

        $this->table(
            ['chain', 'address', 'active', 'provider', 'kind', 'label'],
            $rows,
        );
    }

    private function printSendsSection(User $user, int $limit): void
    {
        $this->info(sprintf('Recent wallet send records (limit %d)', $limit));

        $sends = WalletSendRecord::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->get();

        if ($sends->isEmpty()) {
            $this->line('  (no send records — user has not used /v1/wallet/transactions/prepare yet)');

            return;
        }

        $rows = $sends->map(function (WalletSendRecord $r): array {
            return [
                $r->public_id,
                $r->network,
                $r->asset,
                $r->amount,
                $r->status,
                $r->tx_hash !== null ? substr($r->tx_hash, 0, 14) . '…' : '-',
                $r->error_code ?? '-',
                $r->created_at->toIso8601String(),
            ];
        })->toArray();

        $this->table(
            ['intent_id', 'network', 'asset', 'amount', 'status', 'tx_hash', 'error', 'created_at'],
            $rows,
        );
    }
}
